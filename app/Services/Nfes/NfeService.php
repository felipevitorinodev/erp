<?php

namespace App\Services\Nfes;

use App\Http\Requests\Nfes\NfeCreateRequest;
use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Vendas\Venda;
use App\Repositories\Nfes\NfeRepository;
use App\Services\Empresas\EmpresaService;
use App\Services\Nfces\NfceBuilderService;
use App\Services\Vendas\VendaService;
use App\Support\MensagemPublica;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use NFePHP\Common\Certificate;
use NFePHP\Common\Keys;
use NFePHP\DA\NFe\Danfe;
use RuntimeException;
use Throwable;

class NfeService
{
    public function __construct(
        protected EmpresaService $empresaService,
        protected VendaService $vendaService,
        protected NfePendenciasService $pendenciasService,
        protected NfeRepository $repository,
    ) {}

    public function index(Request $request)
    {
        $empresaId = (int) auth()->user()->empresa_id;

        $nfes = $this->repository->paginar($empresaId);
        $rascunhos = $this->repository->rascunhos($empresaId);

        return view('nfe.index', [
            'nfes' => $nfes,
            'rascunhos' => $rascunhos,
        ]);
    }

    public function create(?Venda $rascunho = null)
    {
        $empresa = $this->repository->empresa((int) auth()->user()->empresa_id);
        $alertas = $this->alertasConfiguracao($empresa);
        $avisoCertificado = null;

        if ($alertas === [] && $empresa->certificado_path) {
            $validade = $this->validadeCertificado($empresa);
            if ($validade !== null && $validade->lt(now()->addDays(30))) {
                $avisoCertificado = 'O certificado digital vence em '.$validade->format('d/m/Y').'. Providencie a renovação.';
            }
        }

        if ($rascunho) {
            $this->repository->carregarRascunho($rascunho);
        }

        $clienteId = old('cliente_id', $rascunho?->cliente_id);
        $funcionarioId = old('funcionario_id', $rascunho?->funcionario_id);

        return view('nfe.create', [
            'empresa' => $empresa,
            'alertas' => $alertas,
            'avisoCertificado' => $avisoCertificado,
            'podeEmitir' => $alertas === [],
            'rascunho' => $rascunho,
            'itensIniciais' => $this->itensIniciais($rascunho),
            'clienteInicial' => $clienteId
                ? $this->repository->clienteDaEmpresa((int) $empresa->id, (int) $clienteId)
                : null,
            'funcionarioInicial' => $funcionarioId
                ? $this->repository->funcionarioDaEmpresa((int) $empresa->id, (int) $funcionarioId)
                : null,
        ]);
    }

    /**
     * Itens para reabrir a tela: os reenviados após erro de validação ou os do rascunho.
     *
     * @return list<array<string, mixed>>
     */
    private function itensIniciais(?Venda $rascunho): array
    {
        $numero = static fn ($v) => (float) (str_contains((string) $v, ',')
            ? str_replace(['.', ','], ['', '.'], (string) $v)
            : $v);

        $enviados = old('itens');

        if (is_array($enviados)) {
            $produtos = $this->repository->produtosDaEmpresa(
                (int) auth()->user()->empresa_id,
                array_filter(array_column($enviados, 'produto_id')),
            );

            return collect($enviados)
                ->filter(fn ($item) => isset($item['produto_id']) && $produtos->has((int) $item['produto_id']))
                ->map(fn ($item) => [
                    'produto_id' => (int) $item['produto_id'],
                    'nome' => $produtos[(int) $item['produto_id']]->nome,
                    'codigo' => $produtos[(int) $item['produto_id']]->codigo,
                    'unidade' => $item['unidade'] ?? 'UN',
                    'ean' => $item['ean'] ?? '',
                    'quantidade' => $numero($item['quantidade'] ?? 0),
                    'preco' => $numero($item['preco_unitario'] ?? 0),
                    'desconto' => $numero($item['desconto'] ?? 0),
                    'ncm' => $item['ncm'] ?? '',
                    'cest' => $item['cest'] ?? '',
                    'cfop' => $item['cfop'] ?? '',
                    'origem' => (int) ($item['origem'] ?? 0),
                    'csosn' => $item['csosn'] ?? '',
                    'cst_pis' => $item['cst_pis'] ?? '',
                    'cst_cofins' => $item['cst_cofins'] ?? '',
                ])
                ->values()
                ->all();
        }

        if (! $rascunho) {
            return [];
        }

        return $rascunho->itens->map(fn ($item) => [
            'produto_id' => (int) $item->produto_id,
            'nome' => $item->produto_nome,
            'codigo' => $item->produto_codigo,
            'unidade' => $item->unidade ?: 'UN',
            'ean' => $item->ean ?: '',
            'quantidade' => (float) $item->quantidade,
            'preco' => (float) $item->preco_unitario,
            'desconto' => (float) $item->desconto,
            'ncm' => $item->ncm ?: '',
            'cest' => $item->cest ?: '',
            'cfop' => $item->cfop ?: '',
            'origem' => (int) ($item->origem ?? 0),
            'csosn' => $item->csosn ?: '',
            'cst_pis' => $item->cst_pis ?: '',
            'cst_cofins' => $item->cst_cofins ?: '',
        ])->values()->all();
    }

    public function editarRascunho(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if (! $venda->isRascunhoNfe()) {
            return redirect()->route('nfe.index')->with('error', 'Este rascunho já foi emitido, cancelado ou excluído.');
        }

        return $this->create($venda);
    }

    public function store(NfeCreateRequest $request, ?Venda $rascunho = null)
    {
        if ($rascunho) {
            $this->autorizarVenda($rascunho);
        }

        $empresa = $this->repository->empresa((int) auth()->user()->empresa_id);

        if ($request->ehRascunho()) {
            try {
                $venda = $this->vendaService->salvarRascunhoNfe($request, $rascunho);
            } catch (ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            return redirect()
                ->route('nfe.rascunho.edit', $venda)
                ->with('success', 'Rascunho da NF-e salvo (venda #'.$venda->numero.'). Nenhum estoque foi movimentado e nada foi enviado à SEFAZ.');
        }

        $alertas = $this->alertasConfiguracao($empresa);

        if ($alertas !== []) {
            return back()
                ->withInput()
                ->with('error', $alertas[0]);
        }

        if ($bloqueio = $this->mensagemSefazIndisponivel($empresa)) {
            return back()->withInput()->with('error', $bloqueio);
        }

        try {
            // Pendências desfazem tudo: nada de venda confirmada, estoque baixado ou número consumido.
            $venda = DB::transaction(function () use ($request, $rascunho, $empresa) {
                $venda = $this->vendaService->salvarRascunhoNfe($request, $rascunho);
                $pendencias = $this->pendenciasService->verificar($venda, $empresa);

                if ($pendencias !== []) {
                    throw ValidationException::withMessages(['pendencias' => $pendencias]);
                }

                return $this->vendaService->confirmarRascunhoNfe($venda);
            });
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('Falha ao criar venda para NF-e', [
                'exception' => MensagemPublica::de($e),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Não foi possível gerar a venda da NF-e: '.($e instanceof \Exception && ! $e instanceof QueryException
                    ? MensagemPublica::de($e)
                    : 'verifique os itens e o estoque.'));
        }

        return $this->emitir($venda);
    }

    public function excluirRascunho(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if (! $venda->isRascunhoNfe()) {
            return redirect()->route('nfe.index')->with('error', 'Somente rascunhos ainda não emitidos podem ser excluídos.');
        }

        $this->repository->excluirVenda($venda);

        return redirect()->route('nfe.index')->with('success', 'Rascunho da NF-e excluído.');
    }

    /**
     * @return list<string>
     */
    private function alertasConfiguracao(Empresa $empresa): array
    {
        $alertas = [];

        $arquivo = $empresa->certificado_path
            ? storage_path('app/'.$empresa->certificado_path)
            : '';

        if (! $empresa->certificado_path || ! is_file($arquivo) || blank($empresa->certificado_senha_decrypted)) {
            $alertas[] = 'Configure o certificado digital A1 na empresa antes de emitir a NF-e.';
        } else {
            $validade = $this->validadeCertificado($empresa);

            if ($validade === null) {
                $alertas[] = 'O certificado digital da empresa é inválido ou a senha está incorreta.';
            } elseif ($validade->isPast()) {
                $alertas[] = 'O certificado digital venceu em '.$validade->format('d/m/Y').'. Envie um certificado válido na empresa.';
            }
        }

        if (strlen(preg_replace('/\D/', '', (string) $empresa->cep) ?? '') !== 8) {
            $alertas[] = 'Informe o CEP da empresa com 8 dígitos.';
        }

        if (strlen(preg_replace('/\D/', '', (string) $empresa->cod_ibge) ?? '') !== 7) {
            $alertas[] = 'Informe o código IBGE do município (7 dígitos) na empresa.';
        }

        if (blank($empresa->ie)) {
            $alertas[] = 'Informe a inscrição estadual da empresa.';
        }

        if (blank($empresa->uf) || blank($empresa->cidade) || blank($empresa->logradouro) || blank($empresa->bairro)) {
            $alertas[] = 'Complete o endereço da empresa (logradouro, bairro, cidade e UF).';
        }

        if ((int) $empresa->nfe_destino !== 1) {
            $alertas[] = 'A emissão desta versão cobre apenas operação interna. Ajuste o destino na empresa.';
        }

        if ((int) $empresa->crt === 3) {
            $alertas[] = 'Regime Normal ainda não é emitido nesta versão. Use Simples Nacional ou MEI.';
        }

        return $alertas;
    }

    private function validadeCertificado(Empresa $empresa): ?Carbon
    {
        try {
            $certificado = Certificate::readPfx(
                (string) file_get_contents(storage_path('app/'.$empresa->certificado_path)),
                (string) $empresa->certificado_senha_decrypted
            );

            return Carbon::instance($certificado->getValidTo());
        } catch (Throwable) {
            return null;
        }
    }

    public function show(Nfe $nfe)
    {
        $this->autorizarNfe($nfe);
        $this->repository->carregarVenda($nfe);

        return view('nfe.show', [
            'nfe' => $nfe,
        ]);
    }

    public function emitir(Venda $venda)
    {
        $this->autorizarVenda($venda);

        $empresa = $this->repository->empresa((int) $venda->empresa_id);

        if ($bloqueio = $this->mensagemSefazIndisponivel($empresa)) {
            return back()->with('error', $bloqueio);
        }

        $nfe = DB::transaction(function () use ($venda, $empresa) {
            $venda = $this->repository->travarVenda((int) $venda->id);

            if ($venda->situacao !== 'confirmada') {
                return 'Apenas vendas confirmadas podem ter NF-e emitida.';
            }

            $nfes = $this->repository->notasDaVenda((int) $venda->empresa_id, (int) $venda->id);

            if ($nfes->contains('situacao', 'autorizada')) {
                return 'Esta venda já possui NF-e autorizada.';
            }

            if ($nfes->contains('situacao', 'pendente')) {
                return 'Esta venda já possui uma NF-e em andamento. Use "Consultar SEFAZ" na NF-e pendente.';
            }

            $ambiente = (int) ($empresa->nfe_ambiente ?: 2);
            $serie = $empresa->nfe_serie ?: '001';
            $ultima = $nfes->first(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFE);

            // Nota rejeitada não consome o número na SEFAZ: reaproveita para não abrir buraco na numeração.
            // 539 = o número já foi usado por outra chave na SEFAZ, então não pode ser reaproveitado.
            if ($ultima
                && $ultima->situacao === 'rejeitada'
                && (int) $ultima->ambiente === $ambiente
                && $ultima->serie === $serie
                && $ultima->codigo_status !== '539') {
                $this->repository->reabrirRejeitada($ultima);
                $this->repository->definirNumeroDaVenda($venda, (string) $ultima->numero);

                return $ultima;
            }

            $numero = $this->empresaService->proximoNumeronNfe($empresa);

            $this->repository->definirNumeroDaVenda($venda, $numero);

            return $this->repository->criarPendente(
                (int) $empresa->id,
                (int) $venda->id,
                $numero,
                $serie,
                $ambiente,
            );
        });

        if (is_string($nfe)) {
            return back()->with('error', $nfe);
        }

        $venda = $this->repository->recarregarVenda($venda);
        $this->gravarCodigosResolvidos($venda, $empresa);

        try {
            $builder = new NfeBuilderService($empresa);
            $signedXml = $builder->buildAndSign($this->repository->carregarParaMontagem($venda));

            $this->repository->atualizar($nfe, [
                'xml_assinado' => $signedXml,
                'chave_acesso' => $this->extrairChave($signedXml),
            ]);
        } catch (RuntimeException $e) {
            $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => mb_substr(MensagemPublica::de($e), 0, 255),
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', MensagemPublica::de($e));
        } catch (Throwable $e) {
            Log::error('Falha ao montar NF-e', [
                'venda_id' => $venda->id,
                'exception' => MensagemPublica::de($e),
            ]);

            $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => 'Não foi possível montar a NF-e.',
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'Não foi possível montar a NF-e. Verifique os dados fiscais da empresa e dos produtos.');
        }

        $resultado = (new NfeSenderService($empresa, $builder->tools()))->enviar($signedXml, $empresa);

        return $this->aplicarResultadoEnvio($nfe, $resultado);
    }

    public function consultar(Nfe $nfe)
    {
        $this->autorizarNfe($nfe);

        if ($nfe->situacao !== 'pendente') {
            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'Somente NF-e pendente pode ser consultada para atualizar a situação.');
        }

        if (blank($nfe->chave_acesso) || blank($nfe->xml_assinado)) {
            // Sem XML assinado a nota nunca foi transmitida.
            $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'motivo_status' => 'A NF-e não chegou a ser transmitida à SEFAZ.',
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'A NF-e não chegou a ser transmitida. Você pode emitir novamente pela venda.');
        }

        $empresa = $this->repository->empresa((int) $nfe->empresa_id);

        try {
            $sender = new NfeSenderService($empresa);
        } catch (RuntimeException $e) {
            return redirect()->route('nfe.show', $nfe)->with('error', MensagemPublica::de($e));
        }

        $resultado = $sender->consultar($nfe->chave_acesso, $nfe->xml_assinado, (int) $nfe->ambiente);

        return $this->aplicarResultadoEnvio($nfe, $resultado);
    }

    public function xml(Nfe $nfe, bool $cancelamento = false)
    {
        $this->autorizarNfe($nfe);

        $conteudo = $cancelamento ? $nfe->xml_cancelamento : $nfe->xml_autorizado;

        if (blank($conteudo)) {
            return back()->with('error', 'XML não disponível para esta NF-e.');
        }

        $nome = ($nfe->chave_acesso ?: $nfe->numero).($cancelamento ? '-procEventoNFe' : '-nfe').'.xml';

        return response($conteudo, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }

    public function cancelar(Nfe $nfe, string $justificativa)
    {
        $this->autorizarNfe($nfe);

        if (! $nfe->podeCancelar()) {
            return back()->with('error', 'Só é possível cancelar NF-e autorizada nas últimas 24 horas.');
        }

        if (blank($nfe->chave_acesso) || blank($nfe->protocolo)) {
            return back()->with('error', 'NF-e sem chave ou protocolo para cancelamento.');
        }

        $empresa = $this->repository->empresa((int) $nfe->empresa_id);

        try {
            $sender = new NfeSenderService($empresa);
        } catch (RuntimeException $e) {
            return back()->with('error', MensagemPublica::de($e));
        }

        $resultado = $sender->cancelar($nfe->chave_acesso, $justificativa, $nfe->protocolo, (int) $nfe->ambiente);

        if (($resultado['status'] ?? '') === 'cancelada') {
            $this->repository->atualizar($nfe, [
                'situacao' => 'cancelada',
                'codigo_status' => $resultado['codigo'] ?? '101',
                'motivo_status' => mb_substr((string) ($resultado['motivo'] ?? 'Cancelamento homologado'), 0, 255),
                'xml_cancelamento' => $resultado['xml_evento'] ?? null,
                'data_cancelamento' => now(),
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('success', 'NF-e cancelada com sucesso.');
        }

        return back()->with(
            'error',
            'Falha no cancelamento: '.($resultado['motivo'] ?? 'erro desconhecido')
            .(isset($resultado['codigo']) ? ' Código: '.$resultado['codigo'] : '')
        );
    }

    public function danfe(Nfe $nfe)
    {
        $this->autorizarNfe($nfe);

        if (blank($nfe->xml_autorizado)) {
            return back()->with('error', 'NF-e ainda não autorizada.');
        }

        return $this->pdfDaNota($nfe);
    }

    private function pdfDaNota(Nfe $nfe)
    {
        try {
            $pdf = (new Danfe($nfe->xml_autorizado))->render();
        } catch (Throwable $e) {
            Log::error('Falha ao gerar DANFE', [
                'nfe_id' => $nfe->id,
                'exception' => MensagemPublica::de($e),
            ]);

            return back()->with('error', 'Não foi possível gerar o DANFE desta NF-e.');
        }

        $nome = 'danfe_'.$nfe->numero.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }

    private function aplicarResultadoEnvio(Nfe $nfe, array $resultado)
    {
        $status = $resultado['status'] ?? 'erro';
        $motivo = (string) ($resultado['motivo'] ?? 'erro desconhecido');
        $codigo = $resultado['codigo'] ?? null;

        if ($status === 'autorizada' || $status === 'denegada') {
            $this->repository->atualizar($nfe, [
                'situacao' => $status,
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'protocolo' => $resultado['protocolo'] ?? null,
                'xml_autorizado' => $resultado['xml_autorizado'] ?? null,
                'codigo_status' => $codigo ?? '100',
                'motivo_status' => mb_substr($status === 'autorizada' && ! isset($resultado['motivo']) ? 'Autorizado o uso da NF-e' : $motivo, 0, 255),
                'data_autorizacao' => now(),
            ]);

            if ($status === 'denegada') {
                return redirect()
                    ->route('nfe.show', $nfe)
                    ->with('error', 'NF-e denegada pela SEFAZ: '.$motivo.($codigo ? ' Código: '.$codigo : '').'. O número foi consumido e não pode ser reutilizado.');
            }

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('success', 'NF-e autorizada com sucesso.');
        }

        if ($status === 'rejeitada' || $status === 'nao_encontrada') {
            $this->repository->atualizar($nfe, [
                'situacao' => 'rejeitada',
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'codigo_status' => $codigo,
                'motivo_status' => mb_substr($status === 'nao_encontrada'
                    ? 'A NF-e não consta na SEFAZ (não foi autorizada). Pode ser emitida novamente.'
                    : $motivo, 0, 255),
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'NF-e rejeitada: '.$motivo.($codigo ? ' Código: '.$codigo : ''));
        }

        // Timeout, lote em processamento, retorno ilegível, duplicidade: a situação real na SEFAZ é desconhecida.
        // A nota continua pendente para impedir reemissão com outro número antes de consultar.
        $this->repository->atualizar($nfe, [
            'codigo_status' => $codigo,
            'motivo_status' => mb_substr($motivo, 0, 255),
            'recibo' => $resultado['recibo'] ?? $nfe->recibo,
        ]);

        return redirect()
            ->route('nfe.show', $nfe)
            ->with('warning', 'Não foi possível confirmar a situação da NF-e na SEFAZ: '.$motivo.' Use "Consultar SEFAZ" antes de tentar emitir novamente.');
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

    private function autorizarNfe(Nfe $nfe): void
    {
        if ((int) $nfe->empresa_id !== (int) auth()->user()->empresa_id || (int) $nfe->modelo === Nfe::MODELO_NFCE) {
            abort(404);
        }
    }

    private function autorizarVenda(Venda $venda): void
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function mensagemSefazIndisponivel(Empresa $empresa): ?string
    {
        if (app()->bound('nfe.sefaz.status')) {
            return app('nfe.sefaz.status')($empresa);
        }

        try {
            return (new NfeSenderService($empresa))->motivoSeIndisponivel();
        } catch (Throwable) {
            return null;
        }
    }

    private function gravarCodigosResolvidos(Venda $venda, Empresa $empresa): void
    {
        $this->repository->carregarItensFiscais($venda);
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

    public function limparCodigosFiscais(Nfe $nfe)
    {
        $this->autorizarNfe($nfe);

        if ($nfe->situacao !== 'rejeitada' || ! $nfe->venda_id) {
            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'Só uma NF-e rejeitada, ainda sem autorização, pode ter os códigos da venda limpos.');
        }

        if ($this->repository->vendaTemNotaBloqueandoLimpeza((int) $nfe->empresa_id, (int) $nfe->venda_id)) {
            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'Esta venda tem nota autorizada, pendente ou cancelada. Os códigos não foram alterados.');
        }

        $venda = $this->repository->vendaDaEmpresa((int) $nfe->empresa_id, (int) $nfe->venda_id);
        $this->repository->limparCodigosDaVenda($venda);

        return redirect()
            ->route('nfe.show', $nfe)
            ->with('success', 'CFOP, CSOSN e CST dos itens foram limpos. A próxima emissão usa o perfil atual. O XML desta nota rejeitada não mudou.');
    }

    public function formularioInutilizacao()
    {
        $empresa = $this->repository->empresa((int) auth()->user()->empresa_id);

        return view('nfe.inutilizar', [
            'empresa' => $empresa,
            'inutilizacoes' => $this->repository->inutilizacoes((int) $empresa->id),
            'modeloPadrao' => request()->integer('modelo') === Nfe::MODELO_NFCE ? Nfe::MODELO_NFCE : Nfe::MODELO_NFE,
        ]);
    }

    public function inutilizar(Request $request)
    {
        $empresa = $this->repository->empresa((int) auth()->user()->empresa_id);
        $dados = $request->validate([
            'modelo' => ['nullable', 'in:'.Nfe::MODELO_NFE.','.Nfe::MODELO_NFCE],
            'serie' => ['required', 'regex:/^\d{1,3}$/'],
            'ano' => ['required', 'regex:/^\d{2}$/'],
            'numero_inicial' => ['required', 'integer', 'min:1', 'max:999999999'],
            'numero_final' => ['required', 'integer', 'min:1', 'max:999999999', 'gte:numero_inicial'],
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ], [
            'justificativa.min' => 'A justificativa deve ter no mínimo 15 caracteres.',
            'numero_final.gte' => 'O número final precisa ser maior ou igual ao inicial.',
        ]);

        // Sem modelo no envio vale a NF-e (como antes). NFC-e usa o Tools do modelo 65.
        $modelo = (int) ($dados['modelo'] ?? Nfe::MODELO_NFE);
        $nomeModelo = $modelo === Nfe::MODELO_NFCE ? 'NFC-e' : 'NF-e';
        $serie = str_pad($dados['serie'], 3, '0', STR_PAD_LEFT);
        $inicio = (int) $dados['numero_inicial'];
        $fim = (int) $dados['numero_final'];
        $ambiente = (int) ($empresa->nfe_ambiente ?: 2);

        if ($fim - $inicio > 1000) {
            return back()->withInput()->with('error', 'A faixa pode ter no máximo 1000 números por envio.');
        }

        if ($this->repository->faixaOcupada((int) $empresa->id, $ambiente, $serie, $inicio, $fim, $modelo)) {
            return back()->withInput()->with('error', 'Essa faixa tem uma '.$nomeModelo.' já gravada nesta série e ambiente. Se a nota foi rejeitada, emita de novo pela venda: o mesmo número é usado. Número rejeitado com código 539 não entra na inutilização por aqui.');
        }

        try {
            $tools = $modelo === Nfe::MODELO_NFCE ? (new NfceBuilderService($empresa))->tools() : null;
            $resultado = (new NfeSenderService($empresa, $tools))->inutilizar(
                (int) $serie,
                $inicio,
                $fim,
                trim($dados['justificativa']),
                $ambiente,
                $dados['ano']
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', MensagemPublica::de($e));
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Não foi possível inutilizar. Verifique o certificado e a conexão com a SEFAZ.');
        }

        $this->repository->criarInutilizacao([
            'empresa_id' => $empresa->id,
            'modelo' => $modelo,
            'ambiente' => $ambiente,
            'serie' => $serie,
            'ano' => $dados['ano'],
            'numero_inicial' => $inicio,
            'numero_final' => $fim,
            'justificativa' => trim($dados['justificativa']),
            'situacao' => $resultado['status'],
            'protocolo' => $resultado['protocolo'],
            'codigo_status' => $resultado['codigo'] !== '' ? $resultado['codigo'] : null,
            'motivo_status' => mb_substr($resultado['motivo'], 0, 255),
            'xml' => $resultado['xml'],
        ]);

        $voltar = redirect()->route('nfe.inutilizar', $modelo === Nfe::MODELO_NFCE ? ['modelo' => $modelo] : []);

        if ($resultado['status'] === 'autorizada') {
            return $voltar->with('success', 'Faixa de '.$nomeModelo.' inutilizada. Protocolo '.$resultado['protocolo'].'.');
        }

        return $voltar->with('error', $resultado['motivo']);
    }
}
