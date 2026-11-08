<?php

namespace App\Services\Nfces;

use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Vendas\Venda;
use App\Repositories\Nfces\NfceRepository;
use App\Services\Empresas\EmpresaService;
use App\Services\Nfes\NfeBuilderService;
use App\Services\Nfes\NfeSenderService;
use App\Services\Nfes\NfeVinculoFiscal;
use App\Support\Auditoria;
use App\Support\MensagemPublica;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Keys;
use NFePHP\DA\NFe\Danfce;
use RuntimeException;
use Throwable;

class NfceService
{
    public function __construct(
        protected EmpresaService $empresaService,
        protected NfceRepository $repository,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    public function validarAntesDaVenda(Empresa $empresa, array $itens, float $total, ?string $documento): void
    {
        $mensagem = $this->mensagemBloqueio($empresa, $itens, $total, $documento);
        if ($mensagem === null) {
            return;
        }

        $campo = str_contains($mensagem, 'CPF') || str_contains($mensagem, 'CNPJ')
            ? 'nfce_documento'
            : 'nfce';

        throw ValidationException::withMessages([$campo => $mensagem]);
    }

    /**
     * @return array{ok: bool, situacao: ?string, mensagem: string, numero: ?string, chave: ?string, danfe_url: ?string}
     */
    public function emitir(Venda $venda, ?string $documento = null, float $valorRecebido = 0): array
    {
        $this->autorizarVenda($venda);
        $empresa = $this->repository->empresa((int) $venda->empresa_id);
        $venda->loadMissing(['itens.produto.perfilFiscal', 'cliente']);

        if ($venda->situacao !== 'confirmada') {
            return $this->resposta(false, null, 'Apenas vendas confirmadas podem emitir NFC-e.');
        }

        $documento = $this->resolverDocumento($venda, $documento);
        $bloqueio = $this->mensagemBloqueio(
            $empresa,
            $this->itensDaVenda($venda),
            (float) $venda->total,
            $documento,
        );
        if ($bloqueio !== null) {
            return $this->resposta(false, null, $bloqueio);
        }

        try {
            $builder = new NfceBuilderService($empresa);
        } catch (RuntimeException $e) {
            return $this->resposta(false, null, MensagemPublica::de($e));
        }

        if ($sefaz = $this->mensagemSefazIndisponivel($empresa, $builder)) {
            return $this->resposta(false, null, $sefaz);
        }

        $nfe = DB::transaction(function () use ($venda, $empresa) {
            $venda = $this->repository->travarVenda((int) $venda->id);
            $ambiente = (int) ($empresa->nfe_ambiente ?: 2);
            $serie = $this->serie($empresa);

            $nfes = $this->repository->daVenda((int) $venda->empresa_id, (int) $venda->id);

            if ($nfes->contains(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFE && $nota->situacao === 'autorizada')) {
                return 'Esta venda já possui NF-e autorizada. A NFC-e não foi emitida.';
            }

            if ($nfes->contains(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFCE && $nota->situacao === 'autorizada')) {
                return 'Esta venda já possui NFC-e autorizada.';
            }

            if ($nfes->where('situacao', 'pendente')->isNotEmpty()) {
                return 'Esta venda já tem uma nota pendente. Use "Consultar SEFAZ" no quadro Nota fiscal da venda antes de emitir de novo. Nenhum número novo foi usado.';
            }

            if ($pendente = $this->repository->pendenteDaSerie((int) $empresa->id, $ambiente, $serie)) {
                return 'A NFC-e da venda nº '.($pendente->venda?->numero ?? '—').' está pendente na SEFAZ. Abra essa venda e use "Consultar SEFAZ" antes de emitir outra. Nenhum número novo foi usado.';
            }

            $ultima = $nfes->first(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFCE);
            if ($ultima
                && $ultima->situacao === 'rejeitada'
                && (int) $ultima->ambiente === $ambiente
                && $ultima->serie === $serie
                && $ultima->codigo_status !== '539') {
                return $this->repository->reabrirRejeitada($ultima);
            }

            $numero = $this->empresaService->proximoNumeronNfe($empresa, Nfe::MODELO_NFCE);

            return $this->repository->criarPendente(
                (int) $empresa->id,
                (int) $venda->id,
                $numero,
                $serie,
                $ambiente,
            );
        });

        if (is_string($nfe)) {
            return $this->resposta(false, null, $nfe);
        }

        $venda = $this->repository->recarregarVenda($venda);
        $this->gravarCodigosResolvidos($venda, $empresa);

        try {
            $signedXml = $builder->buildAndSignNfce(
                $this->repository->carregarItensFiscais($venda),
                (string) $nfe->numero,
                $valorRecebido,
                $documento,
            );
            $nfe = $this->repository->atualizar($nfe, [
                'xml_assinado' => $signedXml,
                'chave_acesso' => $this->extrairChave($signedXml),
            ]);
        } catch (RuntimeException $e) {
            $nfe = $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => mb_substr(MensagemPublica::de($e), 0, 255),
            ]);

            return $this->resposta(false, $nfe, MensagemPublica::de($e));
        } catch (Throwable $e) {
            Log::error('Falha ao montar NFC-e', [
                'venda_id' => $venda->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);
            $nfe = $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => 'Não foi possível montar a NFC-e.',
            ]);

            return $this->resposta(false, $nfe, 'Não foi possível montar a NFC-e. Verifique os dados fiscais da empresa e dos produtos.');
        }

        $resultado = (new NfeSenderService($empresa, $builder->tools()))->enviar($signedXml, $empresa);
        $nfe = $this->gravarResultado($nfe, $resultado);

        return $this->resposta(
            $nfe->situacao === 'autorizada',
            $nfe,
            $this->mensagemResultado($nfe),
        );
    }

    /**
     * Resolve a NFC-e que ficou pendente (sem resposta da SEFAZ): pergunta à SEFAZ pela chave.
     *
     * @return array{ok: bool, situacao: ?string, mensagem: string, numero: ?string, chave: ?string, danfe_url: ?string}
     */
    public function consultar(Nfe $nfe): array
    {
        $this->autorizarNota($nfe);

        if ($nfe->situacao !== 'pendente') {
            return $this->resposta($nfe->situacao === 'autorizada', $nfe, 'Esta NFC-e não está pendente. Nada foi alterado.');
        }

        if (blank($nfe->chave_acesso) || blank($nfe->xml_assinado)) {
            // Sem XML assinado a nota nunca foi transmitida: o número volta a ser usado na próxima emissão.
            $nfe = $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => 'A NFC-e não chegou a ser transmitida à SEFAZ.',
            ]);

            return $this->resposta(false, $nfe, 'A NFC-e não chegou a ser enviada à SEFAZ. Você pode emitir de novo: o mesmo número será usado.');
        }

        try {
            $sender = $this->sender($this->repository->empresa((int) $nfe->empresa_id));
        } catch (RuntimeException $e) {
            return $this->resposta(false, $nfe, MensagemPublica::de($e));
        }

        $resultado = $sender->consultar($nfe->chave_acesso, $nfe->xml_assinado, (int) $nfe->ambiente);
        $nfe = $this->gravarResultado($nfe, $resultado);

        return $this->resposta($nfe->situacao === 'autorizada', $nfe, $this->mensagemConsulta($nfe));
    }

    /**
     * Cancela a NFC-e autorizada na SEFAZ (evento 110111). A venda continua confirmada:
     * cancelar a venda é outro passo, liberado depois deste.
     *
     * @return array{ok: bool, situacao: ?string, mensagem: string, numero: ?string, chave: ?string, danfe_url: ?string}
     */
    public function cancelar(Nfe $nfe, string $justificativa): array
    {
        $this->autorizarNota($nfe);

        if ($nfe->situacao !== 'autorizada') {
            return $this->resposta(false, $nfe, 'Só uma NFC-e autorizada pode ser cancelada.');
        }

        if (! $nfe->podeCancelar()) {
            return $this->resposta(false, $nfe, 'O prazo para cancelar esta NFC-e já passou. Fale com o contador sobre a nota de devolução.');
        }

        if (blank($nfe->chave_acesso) || blank($nfe->protocolo)) {
            return $this->resposta(false, $nfe, 'Esta NFC-e não tem chave ou protocolo de autorização. Use "Consultar SEFAZ" antes.');
        }

        try {
            $sender = $this->sender($this->repository->empresa((int) $nfe->empresa_id));
        } catch (RuntimeException $e) {
            return $this->resposta(false, $nfe, MensagemPublica::de($e));
        }

        $resultado = $sender->cancelar($nfe->chave_acesso, $justificativa, $nfe->protocolo, (int) $nfe->ambiente);

        if (($resultado['status'] ?? '') === 'cancelada') {
            $nfe = $this->repository->atualizar($nfe, [
                'situacao' => 'cancelada',
                'codigo_status' => $resultado['codigo'] ?? '135',
                'motivo_status' => mb_substr((string) ($resultado['motivo'] ?? 'Cancelamento homologado'), 0, 255),
                'xml_cancelamento' => $resultado['xml_evento'] ?? null,
                'data_cancelamento' => now(),
            ]);
            Auditoria::registrar('nfce.cancelada', 'nfe', $nfe->id, ['venda_id' => $nfe->venda_id, 'numero' => $nfe->numero]);

            return $this->resposta(true, $nfe, 'NFC-e nº '.(int) $nfe->numero.' cancelada na SEFAZ.');
        }

        $codigo = (string) ($resultado['codigo'] ?? '');
        $mensagem = 'A SEFAZ não aceitou o cancelamento: '.rtrim((string) ($resultado['motivo'] ?? 'motivo não informado'), '.')
            .($codigo !== '' ? ' (código '.$codigo.').' : '.');
        if ($codigo === '501') {
            $mensagem .= ' O prazo de cancelamento acabou. Fale com o contador sobre a nota de devolução.';
        }

        return $this->resposta(false, $nfe, $mensagem);
    }

    /** XML autorizado (ou o do evento de cancelamento) para o contador. */
    public function xml(Nfe $nfe, bool $cancelamento = false)
    {
        $this->autorizarNota($nfe);

        $conteudo = $cancelamento ? $nfe->xml_cancelamento : $nfe->xml_autorizado;
        if (blank($conteudo)) {
            return back()->with('error', 'O XML desta NFC-e não está disponível.');
        }

        $nome = ($nfe->chave_acesso ?: $nfe->numero).($cancelamento ? '-procEventoNFe' : '-nfce').'.xml';

        return response($conteudo, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }

    public function danfe(Venda $venda)
    {
        $this->autorizarVenda($venda);

        $nfe = $this->repository->autorizadaDaVenda((int) $venda->empresa_id, (int) $venda->id);
        if ($nfe === null) {
            abort(404);
        }

        return $this->pdf($nfe);
    }

    /** A empresa tem o mínimo para emitir NFC-e no ambiente atual (certificado, CSC e idCSC). */
    public function configurada(Empresa $empresa): bool
    {
        $producao = (int) ($empresa->nfe_ambiente ?: 2) === 1;

        return filled($empresa->certificado_path)
            && filled($producao ? $empresa->nfce_csc_producao : $empresa->nfce_csc_homologacao)
            && filled($producao ? $empresa->nfce_id_csc_producao : $empresa->nfce_id_csc_homologacao);
    }

    /**
     * Confere a configuração da NFC-e sem emitir nada: certificado, CSC, dados fiscais,
     * notas pendentes e o serviço da SEFAZ para NFC-e. As mensagens não mostram números de documento.
     *
     * @return list<array{ok: bool, aviso?: bool, titulo: string, texto: string}>
     */
    public function verificar(Empresa $empresa): array
    {
        $producao = (int) ($empresa->nfe_ambiente ?: 2) === 1;
        $itens = [[
            'ok' => true,
            'aviso' => ! $producao,
            'titulo' => 'Ambiente',
            'texto' => $producao
                ? 'Produção: as notas têm valor fiscal.'
                : 'Homologação: as notas são de teste e não têm valor fiscal.',
        ]];

        $certificado = $this->conferirCertificado($empresa);
        $itens[] = $certificado;

        $builder = null;
        try {
            $builder = new NfceBuilderService($empresa);
            $builder->validarCadastroNfce([]);
            $itens[] = ['ok' => true, 'titulo' => 'Dados da empresa e CSC', 'texto' => 'Endereço, IBGE, regime e CSC do ambiente estão preenchidos.'];
        } catch (RuntimeException $e) {
            $itens[] = ['ok' => false, 'titulo' => 'Dados da empresa e CSC', 'texto' => MensagemPublica::de($e)];
        }

        $pendentes = $this->repository->pendentes((int) $empresa->id);
        $itens[] = $pendentes->isEmpty()
            ? ['ok' => true, 'titulo' => 'Notas pendentes', 'texto' => 'Nenhuma NFC-e esperando resposta da SEFAZ.']
            : ['ok' => false, 'titulo' => 'Notas pendentes', 'texto' => 'Consulte a NFC-e das vendas nº '
                .$pendentes->map(fn (Nfe $n) => $n->venda?->numero ?? '—')->implode(', ')
                .'. Enquanto houver nota pendente, nenhuma outra NFC-e é emitida.'];

        if (! $certificado['ok'] || $builder === null) {
            $itens[] = ['ok' => false, 'titulo' => 'SEFAZ', 'texto' => 'Não consultada: corrija os itens acima antes.'];

            return $itens;
        }

        $sefaz = $this->mensagemSefazIndisponivel($empresa, $builder);
        $itens[] = $sefaz === null
            ? ['ok' => true, 'titulo' => 'SEFAZ', 'texto' => 'Serviço de NFC-e da '.strtoupper((string) $empresa->uf).' em operação. O certificado foi aceito na comunicação.']
            : ['ok' => false, 'titulo' => 'SEFAZ', 'texto' => $sefaz];

        return $itens;
    }

    private function pdf(Nfe $nfe)
    {
        try {
            $pdf = (new Danfce($nfe->xml_autorizado))->render();
        } catch (Throwable $e) {
            Log::error('Falha ao gerar DANFC-e', [
                'nfe_id' => $nfe->id,
                'exception' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return back()->with('error', 'Não foi possível gerar o DANFC-e desta NFC-e.');
        }

        $nome = 'nfce_'.$nfe->numero.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    private function mensagemBloqueio(Empresa $empresa, array $itens, float $total, ?string $documento): ?string
    {
        $documento = preg_replace('/\D/', '', (string) $documento) ?? '';
        $documento = $documento === '' ? null : $documento;

        if ($documento !== null && ! NfceBuilderService::documentoValido($documento)) {
            return 'CPF ou CNPJ do consumidor é inválido.';
        }

        if (strtoupper((string) $empresa->uf) === 'PB' && $total + 0.001 >= 500 && $documento === null) {
            return 'Na Paraíba, a NFC-e de R$ 500,00 ou mais precisa do CPF ou CNPJ do consumidor.';
        }

        try {
            $builder = new NfceBuilderService($empresa);
            $builder->validarCadastroNfce($itens);
        } catch (RuntimeException $e) {
            return MensagemPublica::de($e);
        }

        return null;
    }

    private function mensagemSefazIndisponivel(Empresa $empresa, NfceBuilderService $builder): ?string
    {
        if (app()->bound('nfe.sefaz.status')) {
            return app('nfe.sefaz.status')($empresa);
        }

        try {
            $motivo = (new NfeSenderService($empresa, $builder->tools()))->motivoSeIndisponivel();
        } catch (Throwable) {
            $motivo = 'Não foi possível consultar o status da SEFAZ. A emissão foi bloqueada e nenhum número foi usado.';
        }

        // Sem contingência na NFC-e: a nota é emitida depois, pela tela da venda.
        return $motivo === null
            ? null
            : trim(str_replace('Contingência (SVC ou EPEC) não está habilitada: tpEmis permanece 1.', '', $motivo))
                .' Tente emitir de novo em alguns minutos.';
    }

    /** Envio, consulta e cancelamento usam o Tools da NFC-e (modelo 65 e endereços da NFC-e na SEFAZ). */
    private function sender(Empresa $empresa): NfeSenderService
    {
        return new NfeSenderService($empresa, (new NfceBuilderService($empresa))->tools());
    }

    /** @return array{ok: bool, titulo: string, texto: string} */
    private function conferirCertificado(Empresa $empresa): array
    {
        $titulo = 'Certificado digital';
        $arquivo = $empresa->certificado_path ? storage_path('app/'.$empresa->certificado_path) : '';
        $senha = $empresa->certificado_senha_decrypted;

        if ($arquivo === '' || ! is_file($arquivo) || blank($senha)) {
            return ['ok' => false, 'titulo' => $titulo, 'texto' => 'Envie o certificado A1 (.pfx) e a senha na seção Certificado digital.'];
        }

        try {
            $certificado = Certificate::readPfx((string) file_get_contents($arquivo), (string) $senha);
        } catch (Throwable) {
            return ['ok' => false, 'titulo' => $titulo, 'texto' => 'O certificado não abriu: a senha está errada ou o arquivo está danificado.'];
        }

        $validade = $certificado->getValidTo();
        if ($certificado->isExpired()) {
            return ['ok' => false, 'titulo' => $titulo, 'texto' => 'O certificado venceu em '.$validade->format('d/m/Y').'. Renove antes de emitir.'];
        }

        $cnpjCertificado = preg_replace('/\D/', '', (string) $certificado->getCnpj()) ?? '';
        $cnpjEmpresa = preg_replace('/\D/', '', (string) $empresa->cnpj) ?? '';
        if ($cnpjCertificado !== '' && substr($cnpjCertificado, 0, 8) !== substr($cnpjEmpresa, 0, 8)) {
            return ['ok' => false, 'titulo' => $titulo, 'texto' => 'O certificado é de outra empresa: o CNPJ dele não confere com o desta empresa.'];
        }

        $dias = (int) now()->startOfDay()->diffInDays($validade, false);

        return [
            'ok' => true,
            'aviso' => $dias <= 30,
            'titulo' => $titulo,
            'texto' => 'Válido até '.$validade->format('d/m/Y').($dias <= 30 ? ' (faltam '.$dias.' dias: providencie a renovação).' : '.'),
        ];
    }

    private function gravarResultado(Nfe $nfe, array $resultado): Nfe
    {
        $status = $resultado['status'] ?? 'erro';
        $motivo = (string) ($resultado['motivo'] ?? 'erro desconhecido');
        $codigo = $resultado['codigo'] ?? null;

        if ($status === 'autorizada' || $status === 'denegada') {
            return $this->repository->atualizar($nfe, [
                'situacao' => $status,
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'protocolo' => $resultado['protocolo'] ?? null,
                'xml_autorizado' => $resultado['xml_autorizado'] ?? null,
                'codigo_status' => $codigo ?? '100',
                'motivo_status' => mb_substr($status === 'autorizada' && ! isset($resultado['motivo']) ? 'Autorizado o uso da NFC-e' : $motivo, 0, 255),
                'data_autorizacao' => now(),
            ]);
        }

        if ($status === 'rejeitada' || $status === 'nao_encontrada') {
            return $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'codigo_status' => $codigo,
                'motivo_status' => mb_substr($motivo, 0, 255),
            ]);
        }

        return $this->repository->atualizar($nfe, [
            'codigo_status' => $codigo,
            'motivo_status' => mb_substr($motivo, 0, 255),
            'recibo' => $resultado['recibo'] ?? $nfe->recibo,
        ]);
    }

    private function mensagemResultado(Nfe $nfe): string
    {
        $motivo = rtrim($nfe->motivoParaTela(), '.');
        $codigo = $nfe->codigo_status ? ' (código '.$nfe->codigo_status.').' : ($motivo !== '' ? '.' : '');

        return match ($nfe->situacao) {
            'autorizada' => 'NFC-e nº '.(int) $nfe->numero.' autorizada.',
            'rejeitada' => 'NFC-e rejeitada: '.($motivo !== '' ? $motivo : 'a SEFAZ não informou o motivo.').$codigo,
            'denegada' => 'NFC-e denegada: '.$motivo.$codigo.' O número foi consumido.',
            // O motivo técnico fica gravado e aparece no quadro da venda; aqui basta dizer o que fazer.
            default => 'A SEFAZ não respondeu a tempo e a NFC-e ficou pendente. Use "Consultar SEFAZ" na tela da venda antes de emitir outra.',
        };
    }

    private function mensagemConsulta(Nfe $nfe): string
    {
        $motivo = trim((string) $nfe->motivo_status);

        return match (true) {
            $nfe->situacao === 'autorizada' => 'NFC-e nº '.(int) $nfe->numero.' autorizada. Já pode imprimir.',
            $nfe->situacao === 'rejeitada' && $nfe->codigo_status === '217' => 'A SEFAZ não recebeu esta NFC-e. Você pode emitir de novo: o mesmo número será usado.',
            $nfe->situacao === 'pendente' => trim('A SEFAZ ainda não confirmou esta NFC-e. '.$motivo).' Tente consultar de novo em alguns minutos.',
            default => $this->mensagemResultado($nfe),
        };
    }

    /**
     * @return array{ok: bool, situacao: ?string, mensagem: string, numero: ?string, chave: ?string, danfe_url: ?string}
     */
    private function resposta(bool $ok, ?Nfe $nfe, string $mensagem): array
    {
        return [
            'ok' => $ok,
            'situacao' => $nfe?->situacao,
            'mensagem' => $mensagem,
            'numero' => $nfe?->numero,
            'chave' => $nfe?->chave_acesso,
            'danfe_url' => $nfe?->situacao === 'autorizada' && $nfe->venda_id ? route('venda.nfce.danfe', $nfe->venda_id) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function itensDaVenda(Venda $venda): array
    {
        return $venda->itens->map(fn ($item) => [
            'produto_id' => $item->produto_id,
            'produto_nome' => $item->produto_nome,
            'ncm' => $item->ncm,
            'ean' => $item->ean,
            'cfop' => $item->cfop,
            'csosn' => $item->csosn,
            'cst_pis' => $item->cst_pis,
            'cst_cofins' => $item->cst_cofins,
        ])->all();
    }

    private function resolverDocumento(Venda $venda, ?string $informado): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $informado) ?? '';
        if ($digitos === '' && $venda->cliente) {
            $cnpj = preg_replace('/\D/', '', (string) $venda->cliente->cnpj) ?? '';
            $cpf = preg_replace('/\D/', '', (string) $venda->cliente->cpf) ?? '';
            $digitos = strlen($cnpj) === 14 ? $cnpj : $cpf;
        }

        return $digitos === '' ? null : $digitos;
    }

    private function gravarCodigosResolvidos(Venda $venda, Empresa $empresa): void
    {
        $venda = $this->repository->carregarItensFiscais($venda);
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa)->usarVenda($venda);

        foreach ($venda->itens as $item) {
            $produto = $item->produto;
            $dados = [];
            $campos = [
                'cfop' => $vinculo->cfop($item, $produto)['valor'],
                'csosn' => $vinculo->csosn($item, $produto)['valor'],
                'cst_pis' => $vinculo->cst($item, $produto, 'cst_pis', 'cst_pis', 'cst_pis', 'nfe_cst_pis_padrao')['valor'],
                'cst_cofins' => $vinculo->cst($item, $produto, 'cst_cofins', 'cst_cofins', 'cst_cofins', 'nfe_cst_cofins_padrao')['valor'],
            ];

            foreach ($campos as $campo => $valor) {
                if (blank($item->{$campo}) && $valor !== '') {
                    $dados[$campo] = $valor;
                }
            }

            if ($dados !== []) {
                $this->repository->atualizarItem($item, $dados);
            }
        }

        if (blank($venda->nfe_tpag)) {
            $this->repository->gravarTpag($venda, NfeBuilderService::tPag((string) $venda->forma_pagamento));
        }
    }

    private function extrairChave(string $xml): ?string
    {
        try {
            $chave = Keys::extractAccessKey($xml);

            return $chave !== '' ? $chave : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function autorizarVenda(Venda $venda): void
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    /** Só NFC-e (modelo 65) da empresa do usuário; a NF-e tem as rotas dela. */
    private function autorizarNota(Nfe $nfe): void
    {
        if ((int) $nfe->empresa_id !== (int) auth()->user()->empresa_id || (int) $nfe->modelo !== Nfe::MODELO_NFCE) {
            abort(404);
        }
    }

    private function serie(Empresa $empresa): string
    {
        return str_pad(preg_replace('/\D/', '', (string) ($empresa->nfce_serie ?: '001')) ?: '1', 3, '0', STR_PAD_LEFT);
    }
}
