<?php

namespace App\Services\Nfses;

use App\Http\Requests\Vendas\VendaRequest;
use App\Impressao\DanfsePdf;
use App\Models\Empresas\Empresa;
use App\Models\Fiscal\Municipio;
use App\Models\Fiscal\NfseClassificacao;
use App\Models\Nfses\Nfse;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Repositories\Nfses\NfseNumeracaoRepository;
use App\Repositories\Nfses\NfseRepository;
use App\Services\Nfes\NfeDatas;
use App\Services\Vendas\DivisaoFiscalVenda;
use App\Services\Vendas\VendaService;
use App\Support\ItensReenviados;
use App\Support\MensagemPublica;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * NFS-e Padrão Nacional emitida a partir da venda: só os itens de serviço entram (os produtos vão na NF-e).
 * Mesmo desenho da NF-e: confere tudo o que a Sefin recusaria antes de reservar número, reaproveita o número
 * da DPS rejeitada, e o que não tem resposta conclusiva fica pendente até a consulta.
 */
class NfseService
{
    public function __construct(
        protected NfseRepository $repository,
        protected NfseNumeracaoRepository $numeracao,
    ) {}

    public function index(Request $request)
    {
        $empresaId = (int) auth()->user()->empresa_id;

        return view('nfse.index', [
            'nfses' => $this->repository->paginar($empresaId, $request->only('situacao')),
            'situacao' => (string) $request->query('situacao', ''),
        ]);
    }

    /**
     * Tela de conferência antes de emitir: códigos sugeridos, valores, tomador e o que falta corrigir.
     */
    public function create(Venda $venda)
    {
        $this->autorizarVenda($venda);
        $empresa = $this->repository->empresa((int) $venda->empresa_id);
        $venda = $this->repository->carregarVenda($venda);
        $parte = DivisaoFiscalVenda::parte($venda, true);

        if ($parte['itens']->isEmpty()) {
            return redirect()->route('venda.show', $venda)->with('error', 'Esta venda não tem itens de serviço para a NFS-e.');
        }

        $notas = $this->repository->notasDaVenda((int) $empresa->id, (int) $venda->id);
        if ($bloqueio = $this->bloqueioPorNotaExistente($notas)) {
            return redirect()->route('venda.show', $venda)->with('error', $bloqueio);
        }

        $sugestao = NfseCodigos::sugerir($parte['itens'], $empresa);
        $anterior = $notas->first(fn (Nfse $nota) => $nota->situacao === 'rejeitada');
        $entrada = $this->entradaPadrao($venda, $empresa, $parte, $sugestao['codigos'], $anterior);

        $alertas = $this->alertasConfiguracao($empresa);
        if ($venda->situacao !== 'confirmada') {
            $alertas[] = 'Apenas vendas confirmadas podem ter NFS-e emitida.';
        }

        $pendencias = [];
        if ($alertas === [] && $sugestao['bloqueio'] === null) {
            $pendencias = NfseRegras::validar($this->dadosDaEmissao($venda, $empresa, $entrada, $parte));
        }

        return view('nfse.create', [
            'venda' => $venda,
            'empresa' => $empresa,
            'itens' => $parte['itens'],
            'parte' => $parte,
            'valores' => self::valoresDaParte($parte),
            'totalProdutos' => DivisaoFiscalVenda::temProduto($venda) ? DivisaoFiscalVenda::parte($venda, false)['liquido'] : null,
            'entrada' => $entrada,
            'rotulos' => NfseCodigos::rotulos($entrada),
            'municipio' => Municipio::query()->where('codigo', $entrada['municipio_prestacao'])->first(),
            'alertas' => $alertas,
            'bloqueio' => $sugestao['bloqueio'],
            'pendencias' => $pendencias,
            'anterior' => $anterior,
        ] + $this->opcoesDaTela($empresa));
    }

    /**
     * Tela "Nova NFS-e": monta a venda (tomador e serviços) e a nota numa tela só.
     * Os códigos começam com o padrão da empresa; ao escolher um serviço, a tela usa os códigos gravados nele.
     */
    public function nova()
    {
        $empresa = $this->repository->empresa((int) auth()->user()->empresa_id);
        $codigos = NfseCodigos::sugerir(collect(), $empresa)['codigos'];
        $entrada = $codigos + [
            'municipio_prestacao' => preg_replace('/\D/', '', (string) $empresa->cod_ibge) ?? '',
            'descricao' => '',
            'iss_retido' => false,
            'aliquota_iss' => $empresa->nfse_aliquota_iss !== null ? (string) $empresa->nfse_aliquota_iss : '',
            'data_competencia' => now()->toDateString(),
            'memorizar' => true,
        ];

        return view('nfse.nova', [
            'empresa' => $empresa,
            'entrada' => $entrada,
            'municipio' => Municipio::query()->where('codigo', $entrada['municipio_prestacao'])->first(),
            'alertas' => $this->alertasConfiguracao($empresa),
            'itensIniciais' => ItensReenviados::daSessao((int) $empresa->id),
            'formasPagamento' => app(VendaService::class)->formasAtivas(),
        ] + $this->opcoesDaTela($empresa));
    }

    /**
     * Grava a venda confirmada só com os serviços e emite a NFS-e. Se a DPS seria recusada (ou a empresa não
     * está pronta), nada é gravado: a venda volta para a tela com as pendências, sem reservar número.
     *
     * @param  array<string, mixed>  $entrada  campos da NFS-e (NfseNovaRequest::entradaNfse)
     */
    public function salvarNova(VendaRequest $request, array $entrada): RedirectResponse
    {
        $empresa = $this->repository->empresa((int) auth()->user()->empresa_id);
        if ($alertas = $this->alertasConfiguracao($empresa)) {
            return back()->withInput()->with('error', $alertas[0]);
        }

        $venda = DB::transaction(function () use ($request, &$entrada) {
            $venda = $this->repository->carregarVenda(app(VendaService::class)->gravar($request)['venda']);

            if (trim((string) ($entrada['descricao'] ?? '')) === '') {
                $entrada['descricao'] = $this->descricaoDosItens(DivisaoFiscalVenda::parte($venda, true)['itens']);
            }

            if ($pendencias = $this->pendenciasDaEmissao($venda, $entrada)) {
                // Desfaz a venda: o usuário corrige e envia de novo sem duplicar.
                throw ValidationException::withMessages(['nfse' => $pendencias]);
            }

            return $venda;
        });

        return $this->emitir($venda, $entrada);
    }

    /**
     * O que impediria a emissão da NFS-e desta venda com estes dados, sem reservar número nem chamar a Sefin.
     *
     * @param  array<string, mixed>  $entrada
     * @return list<string>
     */
    public function pendenciasDaEmissao(Venda $venda, array $entrada): array
    {
        $empresa = $this->repository->empresa((int) $venda->empresa_id);
        if ($alertas = $this->alertasConfiguracao($empresa)) {
            return $alertas;
        }

        $venda = $this->repository->carregarVenda($venda);
        $parte = DivisaoFiscalVenda::parte($venda, true);
        if ($parte['itens']->isEmpty()) {
            return ['Adicione pelo menos um serviço.'];
        }

        $sugestao = NfseCodigos::sugerir($parte['itens'], $empresa);
        if ($sugestao['bloqueio'] !== null) {
            return [$sugestao['bloqueio']];
        }

        return NfseRegras::validar($this->dadosDaEmissao($venda, $empresa, $this->normalizarEntrada($entrada), $parte));
    }

    /**
     * Regime da empresa e regras da alíquota/retenção que as telas de emissão mostram.
     *
     * @return array<string, mixed>
     */
    private function opcoesDaTela(Empresa $empresa): array
    {
        $op = NfseRegras::opSimpNac($empresa);
        $regAp = NfseRegras::regApTribSN($empresa);
        $regEsp = NfseRegras::regEspTrib($empresa);
        $convenio = NfseRegras::convenioAtivo($empresa);

        return [
            'opSimpNac' => $op,
            'regApTribSN' => $regAp,
            'podeReter' => $op !== NfseRegras::OP_MEI && $regEsp === 0,
            'aliquotaSempre' => NfseRegras::aliquotaObrigatoria($op, $regAp, $regEsp, 1, false, $convenio),
            'aliquotaComRetencao' => NfseRegras::aliquotaObrigatoria($op, $regAp, $regEsp, 1, true, $convenio),
            'explicacaoAliquota' => NfseRegras::explicacaoAliquota($op, $regAp, $convenio),
            'ambiente' => (int) ($empresa->nfse_ambiente ?: 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $entrada  campos validados do NfseEmitirRequest
     */
    public function emitir(Venda $venda, array $entrada): RedirectResponse
    {
        $this->autorizarVenda($venda);
        $empresa = $this->repository->empresa((int) $venda->empresa_id);

        if ($alertas = $this->alertasConfiguracao($empresa)) {
            return redirect()->route('nfse.create', $venda)->withInput()->with('error', $alertas[0]);
        }

        $venda = $this->repository->carregarVenda($venda);
        $parte = DivisaoFiscalVenda::parte($venda, true);

        if ($parte['itens']->isEmpty()) {
            return redirect()->route('venda.show', $venda)->with('error', 'Esta venda não tem itens de serviço para a NFS-e.');
        }

        $sugestao = NfseCodigos::sugerir($parte['itens'], $empresa);
        if ($sugestao['bloqueio'] !== null) {
            return redirect()->route('nfse.create', $venda)->withInput()->with('error', $sugestao['bloqueio']);
        }

        $entrada = $this->normalizarEntrada($entrada);
        $dados = $this->dadosDaEmissao($venda, $empresa, $entrada, $parte);

        if ($pendencias = NfseRegras::validar($dados)) {
            return redirect()->route('nfse.create', $venda)->withInput()->withErrors(['nfse' => $pendencias]);
        }

        $ambiente = $dados['ambiente'];
        $serie = (string) $dados['serie'];

        $nfse = DB::transaction(function () use ($venda, $empresa, $dados, $ambiente, $serie) {
            $travada = $this->repository->travarVenda((int) $venda->id);

            if ($travada->situacao !== 'confirmada') {
                return 'Apenas vendas confirmadas podem ter NFS-e emitida.';
            }

            $notas = $this->repository->notasDaVenda((int) $empresa->id, (int) $venda->id);
            if ($bloqueio = $this->bloqueioPorNotaExistente($notas)) {
                return $bloqueio;
            }

            $registro = $this->registroDaNota($dados);
            $ultima = $notas->first();

            // DPS rejeitada não gera NFS-e: o número volta a valer para a nova tentativa (sem buraco na sequência).
            if ($ultima
                && $ultima->situacao === 'rejeitada'
                && (int) $ultima->ambiente === $ambiente
                && $ultima->serie === $serie
                && blank($ultima->chave_acesso)
                && $ultima->codigo_status !== 'E0014') {
                return $this->repository->atualizar($ultima, $registro + [
                    'situacao' => 'pendente',
                    'codigo_status' => null,
                    'motivo_status' => null,
                    'mensagens' => null,
                    'xml_assinado' => null,
                ]);
            }

            return $this->repository->criar($registro + [
                'empresa_id' => $empresa->id,
                'venda_id' => $venda->id,
                'serie' => $serie,
                'numero' => $this->numeracao->proximo($empresa, $ambiente, $serie),
                'ambiente' => $ambiente,
                'situacao' => 'pendente',
            ]);
        });

        if (is_string($nfse)) {
            return redirect()->route('nfse.create', $venda)->withInput()->with('error', $nfse);
        }

        if ($entrada['memorizar']) {
            $this->memorizar($venda, $empresa, $parte['itens'], $dados);
        }

        $dados['numero'] = (int) $nfse->numero;
        $builder = new NfseBuilderService($empresa);

        try {
            $dps = $builder->dps($dados);
            $this->repository->atualizar($nfse, [
                'xml_assinado' => $dps['xml'],
                'id_dps' => $dps['id'],
                'data_emissao' => $dados['emissao'],
            ]);
        } catch (RuntimeException $e) {
            $this->repository->atualizar($nfse, [
                'situacao' => 'rejeitada',
                'motivo_status' => mb_substr(MensagemPublica::de($e), 0, 255),
            ]);

            return redirect()->route('nfse.show', $nfse)->with('error', MensagemPublica::de($e));
        } catch (Throwable $e) {
            Log::error('Falha ao montar a DPS', ['venda_id' => $venda->id, 'exception' => MensagemPublica::de($e)]);
            $this->repository->atualizar($nfse, [
                'situacao' => 'rejeitada',
                'motivo_status' => 'Não foi possível montar a DPS.',
            ]);

            return redirect()->route('nfse.show', $nfse)->with('error', 'Não foi possível montar a DPS. Confira os dados fiscais da empresa.');
        }

        $sender = new NfseSenderService($builder->certificado());
        $resultado = $sender->emitir($dps['xml'], $ambiente);

        if (($resultado['status'] ?? '') === 'duplicada') {
            // A DPS já virou NFS-e (envio anterior sem resposta): busca a nota em vez de emitir outra.
            $consulta = $sender->consultarDps($dps['id'], $ambiente);
            $resultado = ($consulta['status'] ?? '') === 'autorizada'
                ? $consulta
                : ['status' => 'erro', 'codigo' => 'E0014', 'motivo' => 'A Sefin informa que esta DPS já gerou NFS-e, mas a consulta ainda não trouxe a nota.'];
        }

        return $this->aplicarResultado($nfse, $resultado);
    }

    public function show(Nfse $nfse)
    {
        $this->autorizarNota($nfse);
        $nfse->loadMissing(['venda.cliente', 'empresa']);

        return view('nfse.show', [
            'nfse' => $nfse,
            'servico' => NfseCodigos::rotulos([
                'c_trib_nac' => (string) $nfse->c_trib_nac,
                'nbs' => (string) $nfse->nbs,
                'class_trib' => (string) $nfse->class_trib,
                'ind_op' => (string) $nfse->ind_op,
                'c_trib_mun' => (string) $nfse->c_trib_mun,
            ]),
            'municipio' => $nfse->municipio_prestacao ? Municipio::query()->where('codigo', $nfse->municipio_prestacao)->first() : null,
        ]);
    }

    public function consultar(Nfse $nfse): RedirectResponse
    {
        $this->autorizarNota($nfse);

        if ($nfse->situacao !== 'pendente') {
            return redirect()->route('nfse.show', $nfse)->with('error', 'Somente NFS-e pendente pode ser consultada.');
        }

        if (blank($nfse->xml_assinado) || blank($nfse->id_dps)) {
            $this->repository->atualizar($nfse, [
                'situacao' => 'rejeitada',
                'motivo_status' => 'A DPS não chegou a ser transmitida à Sefin Nacional.',
            ]);

            return redirect()->route('nfse.show', $nfse)->with('error', 'A DPS não chegou a ser transmitida. Você pode emitir novamente pela venda.');
        }

        $empresa = $this->repository->empresa((int) $nfse->empresa_id);

        try {
            $sender = new NfseSenderService((new NfseBuilderService($empresa))->certificado());
        } catch (RuntimeException $e) {
            return redirect()->route('nfse.show', $nfse)->with('error', MensagemPublica::de($e));
        }

        $resultado = $sender->consultarDps((string) $nfse->id_dps, (int) $nfse->ambiente);

        if (($resultado['status'] ?? '') === 'nao_encontrada') {
            $resultado = [
                'status' => 'rejeitada',
                'codigo' => null,
                'motivo' => 'A DPS não consta na Sefin Nacional (não gerou NFS-e). Pode ser emitida novamente.',
            ];
        }

        return $this->aplicarResultado($nfse, $resultado);
    }

    public function cancelar(Nfse $nfse, string $codigoMotivo, string $motivo): RedirectResponse
    {
        $this->autorizarNota($nfse);

        if (! $nfse->podeCancelar()) {
            return back()->with('error', 'Só NFS-e autorizada pode ser cancelada.');
        }

        $empresa = $this->repository->empresa((int) $nfse->empresa_id);

        try {
            $builder = new NfseBuilderService($empresa);
            $evento = $builder->cancelamento(
                (string) $nfse->chave_acesso,
                (int) $nfse->ambiente,
                $codigoMotivo,
                $motivo,
                NfseBuilderService::leiaute((int) $nfse->ambiente)
            );
            $sender = new NfseSenderService($builder->certificado());
        } catch (RuntimeException $e) {
            return back()->with('error', MensagemPublica::de($e));
        }

        $resultado = $sender->cancelar((string) $nfse->chave_acesso, $evento, (int) $nfse->ambiente);

        if (($resultado['status'] ?? '') === 'cancelada') {
            $this->repository->atualizar($nfse, [
                'situacao' => 'cancelada',
                'xml_cancelamento' => $resultado['xml_evento'] ?? $evento,
                'data_cancelamento' => now(),
                'motivo_cancelamento' => mb_substr(Nfse::MOTIVOS_CANCELAMENTO[$codigoMotivo].': '.$motivo, 0, 255),
            ]);

            return redirect()->route('nfse.show', $nfse)->with('success', 'NFS-e cancelada.');
        }

        return back()->with('error', 'A Sefin Nacional não registrou o cancelamento: '.($resultado['motivo'] ?? 'erro desconhecido')
            .(filled($resultado['codigo'] ?? null) ? ' ('.$resultado['codigo'].')' : ''));
    }

    public function xml(Nfse $nfse, string $tipo)
    {
        $this->autorizarNota($nfse);

        [$conteudo, $sufixo] = match ($tipo) {
            'cancelamento' => [$nfse->xml_cancelamento, '-cancelamento'],
            'dps' => [$nfse->xml_assinado, '-dps'],
            default => [$nfse->xml_nfse, '-nfse'],
        };

        if (blank($conteudo)) {
            return back()->with('error', 'XML não disponível para esta NFS-e.');
        }

        $nome = ($nfse->chave_acesso ?: $nfse->id_dps ?: 'nfse-'.$nfse->id).$sufixo.'.xml';

        return response($conteudo, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
    }

    public function danfse(Nfse $nfse)
    {
        $this->autorizarNota($nfse);

        if (blank($nfse->xml_nfse)) {
            return back()->with('error', 'A NFS-e ainda não foi gerada.');
        }

        try {
            $pdf = (new DanfsePdf)->render($nfse);
        } catch (Throwable $e) {
            Log::error('Falha ao gerar o DANFSe', ['nfse_id' => $nfse->id, 'exception' => MensagemPublica::de($e)]);

            return back()->with('error', 'Não foi possível gerar o DANFSe desta NFS-e.');
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="danfse_'.($nfse->numero_nfse ?: $nfse->numero).'.pdf"',
        ]);
    }

    /**
     * "Conferir NFS-e" da tela da empresa: configuração, certificado e convênio do município (consulta à Sefin).
     *
     * @return list<array{ok: bool, aviso?: bool, titulo: string, texto: string}>
     */
    public function verificar(Empresa $empresa): array
    {
        $ambiente = (int) ($empresa->nfse_ambiente ?: 2);
        $itens = [[
            'ok' => true,
            'aviso' => $ambiente !== 1,
            'titulo' => 'Ambiente',
            'texto' => $ambiente === 1
                ? 'Produção: as NFS-e têm valor fiscal (leiaute '.NfseBuilderService::leiaute(1).').'
                : 'Produção restrita (homologação): as NFS-e são de teste (leiaute '.NfseBuilderService::leiaute(2).').',
        ]];

        $alertas = $this->alertasConfiguracao($empresa);
        $itens[] = $alertas === []
            ? ['ok' => true, 'titulo' => 'Dados da empresa', 'texto' => 'Certificado, CNPJ, município, regime e série preenchidos.']
            : ['ok' => false, 'titulo' => 'Dados da empresa', 'texto' => implode(' ', $alertas)];

        $op = NfseRegras::opSimpNac($empresa);
        $itens[] = [
            'ok' => true,
            'titulo' => 'Regime na NFS-e',
            'texto' => match ($op) {
                NfseRegras::OP_MEI => 'MEI: sem alíquota e sem retenção de ISSQN.',
                NfseRegras::OP_ME_EPP => 'ME/EPP do Simples Nacional: '.mb_strtolower(NfseRegras::REGIMES_APURACAO_SN[NfseRegras::regApTribSN($empresa)]).'.',
                default => 'Não optante do Simples Nacional.',
            },
        ];

        if ($alertas !== []) {
            $itens[] = ['ok' => false, 'titulo' => 'Convênio do município', 'texto' => 'Não consultado: corrija os itens acima antes.'];

            return $itens;
        }

        try {
            $sender = new NfseSenderService((new NfseBuilderService($empresa))->certificado());
            $convenio = $sender->convenio(preg_replace('/\D/', '', (string) $empresa->cod_ibge) ?? '', $ambiente);
        } catch (RuntimeException $e) {
            $itens[] = ['ok' => false, 'titulo' => 'Convênio do município', 'texto' => MensagemPublica::de($e)];

            return $itens;
        }

        if (($convenio['status'] ?? '') !== 'ok') {
            $itens[] = ['ok' => false, 'titulo' => 'Convênio do município', 'texto' => 'Não foi possível consultar: '.($convenio['motivo'] ?? 'sem resposta').'.'];

            return $itens;
        }

        $situacao = $convenio['situacao'] ?? null;
        $this->repository->gravarConvenio($empresa, $situacao);

        $itens[] = match ($situacao) {
            'ativo' => ['ok' => true, 'titulo' => 'Convênio do município', 'texto' => 'O município está conveniado ao Sistema Nacional NFS-e. A Sefin aplica a alíquota cadastrada por ele.'],
            'inativo' => ['ok' => false, 'titulo' => 'Convênio do município', 'texto' => ($convenio['motivo'] ?? 'O convênio do município não está ativo.').' A DPS será recusada (E0037) ou exigirá a alíquota do ISSQN.'],
            default => ['ok' => true, 'aviso' => true, 'titulo' => 'Convênio do município', 'texto' => 'A Sefin respondeu, mas sem indicar a situação do convênio. A emissão segue como convênio ativo.'],
        };

        return $itens;
    }

    /**
     * @return list<string>
     */
    public function alertasConfiguracao(Empresa $empresa): array
    {
        $alertas = [];

        try {
            (new NfseBuilderService($empresa))->certificado();
        } catch (RuntimeException $e) {
            $alertas[] = MensagemPublica::de($e);
        }

        $documento = preg_replace('/\D/', '', (string) ($empresa->cnpj ?: $empresa->cpf)) ?? '';
        if (! in_array(strlen($documento), [11, 14], true)) {
            $alertas[] = 'Informe o CNPJ da empresa.';
        }

        if (strlen(preg_replace('/\D/', '', (string) $empresa->cod_ibge) ?? '') !== 7) {
            $alertas[] = 'Informe o código IBGE do município da empresa (7 dígitos).';
        }

        if (! in_array((int) $empresa->crt, [1, 2, 3, 4], true)) {
            $alertas[] = 'Informe o regime tributário (CRT) da empresa.';
        }

        return $alertas;
    }

    /**
     * Situação da NFS-e para o quadro da venda.
     *
     * @return array{temServico: bool, nota: Nfse|null, podeEmitir: bool}
     */
    public function resumoDaVenda(Venda $venda): array
    {
        $temServico = DivisaoFiscalVenda::temServico($venda);
        $nota = $venda->relationLoaded('nfses')
            ? $venda->nfses->sortByDesc('id')->first()
            : $venda->nfses()->orderByDesc('id')->first();

        return [
            'temServico' => $temServico,
            'nota' => $nota,
            'podeEmitir' => $temServico
                && $venda->situacao === 'confirmada'
                && (! $nota || in_array($nota->situacao, ['rejeitada', 'cancelada'], true)),
        ];
    }

    /**
     * Monta tudo o que a DPS precisa a partir da venda, da empresa e do que foi escolhido na tela.
     *
     * @param  array<string, mixed>  $entrada
     * @param  array{itens: Collection, total_itens: float, desconto: float, acrescimo: float, liquido: float}  $parte
     * @return array<string, mixed>
     */
    public function dadosDaEmissao(Venda $venda, Empresa $empresa, array $entrada, array $parte): array
    {
        $fuso = (new NfeDatas)->fusoDaUf($empresa->uf);
        $ambiente = (int) ($empresa->nfse_ambiente ?: 2);
        $op = NfseRegras::opSimpNac($empresa);
        $regAp = NfseRegras::regApTribSN($empresa);
        $regEsp = NfseRegras::regEspTrib($empresa);
        $convenio = NfseRegras::convenioAtivo($empresa);
        $cTribNac = (string) $entrada['c_trib_nac'];
        $trib = NfseRegras::tribIssqn($cTribNac);
        $retido = (bool) $entrada['iss_retido'];
        $aliquotaObrigatoria = NfseRegras::aliquotaObrigatoria($op, $regAp, $regEsp, $trib, $retido, $convenio);

        $competencia = null;
        try {
            $competencia = filled($entrada['data_competencia']) ? Carbon::parse((string) $entrada['data_competencia'], $fuso)->startOfDay() : null;
        } catch (Throwable) {
            $competencia = null;
        }

        $classificacao = NfseClassificacao::query()->where('codigo', (string) $entrada['class_trib'])->first();
        [$tomador, $pendenciaTomador] = $this->tomador($venda);

        return [
            'ambiente' => $ambiente,
            'leiaute' => NfseBuilderService::leiaute($ambiente),
            'serie' => $this->serie($empresa),
            'numero' => 0,
            'emissao' => now($fuso),
            'competencia' => $competencia,
            'convenio_ativo' => $convenio,
            'prestador' => [
                'documento' => preg_replace('/\D/', '', (string) ($empresa->cnpj ?: $empresa->cpf)) ?? '',
                'im' => trim((string) $empresa->im),
                'cod_ibge' => preg_replace('/\D/', '', (string) $empresa->cod_ibge) ?? '',
                'fone' => NfseRegras::telefone($empresa->telefone ?: $empresa->celular),
                'email' => NfseRegras::email($empresa->email),
                'op_simp_nac' => $op,
                'reg_ap_trib_sn' => $regAp,
                'reg_esp_trib' => $regEsp,
            ],
            'tomador' => $tomador,
            'tomador_pendencia' => $pendenciaTomador,
            'servico' => [
                'c_trib_nac' => $cTribNac,
                'c_trib_mun' => (string) $entrada['c_trib_mun'],
                'nbs' => (string) $entrada['nbs'],
                'descricao' => (string) $entrada['descricao'],
                'municipio_prestacao' => (string) $entrada['municipio_prestacao'],
            ],
            'valores' => self::valoresDaParte($parte),
            'iss' => [
                'trib_issqn' => $trib,
                'retido' => $retido,
                'aliquota_obrigatoria' => $aliquotaObrigatoria,
                'aliquota' => $aliquotaObrigatoria && filled($entrada['aliquota_iss']) ? round((float) $entrada['aliquota_iss'], 2) : null,
            ],
            'tot_trib' => match ($op) {
                NfseRegras::OP_MEI => ['tipo' => 'ind'],
                NfseRegras::OP_ME_EPP => ['tipo' => 'sn', 'sn' => $empresa->nfse_ptot_trib_sn !== null ? (float) $empresa->nfse_ptot_trib_sn : null],
                default => [
                    'tipo' => 'percentual',
                    'fed' => $empresa->nfse_ptot_trib_fed !== null ? (float) $empresa->nfse_ptot_trib_fed : null,
                    'est' => $empresa->nfse_ptot_trib_est !== null ? (float) $empresa->nfse_ptot_trib_est : null,
                    'mun' => $empresa->nfse_ptot_trib_mun !== null ? (float) $empresa->nfse_ptot_trib_mun : null,
                ],
            },
            'ibscbs' => [
                'class_trib' => (string) $entrada['class_trib'],
                'cst' => $classificacao?->cst ?? substr((string) $entrada['class_trib'], 0, 3),
                'exige_tributacao' => (bool) ($classificacao?->exige_tributacao ?? true),
                'ind_op' => (string) $entrada['ind_op'],
            ],
        ];
    }

    /**
     * vServ leva o valor cheio dos serviços (com o acréscimo rateado); os descontos dos itens e o rateado da venda
     * vão em vDescIncond.
     *
     * @param  array{itens: Collection, total_itens: float, desconto: float, acrescimo: float, liquido: float}  $parte
     * @return array{servico: float, desconto: float, liquido: float}
     */
    public static function valoresDaParte(array $parte): array
    {
        $descontoItens = round((float) $parte['itens']->sum(fn (VendaItem $item) => (float) $item->desconto), 2);
        $bruto = round($parte['total_itens'] + $descontoItens + $parte['acrescimo'], 2);
        $desconto = round($descontoItens + $parte['desconto'], 2);

        return ['servico' => $bruto, 'desconto' => $desconto, 'liquido' => round($bruto - $desconto, 2)];
    }

    /**
     * @param  Collection<int, Nfse>  $notas
     */
    private function bloqueioPorNotaExistente($notas): ?string
    {
        if ($notas->contains('situacao', 'autorizada')) {
            return 'Esta venda já tem NFS-e autorizada.';
        }

        if ($notas->contains('situacao', 'pendente')) {
            return 'Esta venda já tem uma NFS-e em andamento. Use "Consultar Sefin" na NFS-e pendente.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function aplicarResultado(Nfse $nfse, array $resultado): RedirectResponse
    {
        $status = $resultado['status'] ?? 'erro';
        $motivo = (string) ($resultado['motivo'] ?? 'erro desconhecido');
        $codigo = $resultado['codigo'] ?? null;

        if ($status === 'autorizada') {
            $data = null;
            try {
                $data = filled($resultado['data'] ?? null) ? Carbon::parse((string) $resultado['data']) : null;
            } catch (Throwable) {
                $data = null;
            }

            $this->repository->atualizar($nfse, [
                'situacao' => 'autorizada',
                'chave_acesso' => $resultado['chave'] ?? $nfse->chave_acesso,
                'numero_nfse' => $resultado['numero'] ?? null,
                'xml_nfse' => $resultado['xml_nfse'] ?? null,
                'codigo_status' => '100',
                'motivo_status' => 'NFS-e gerada',
                'mensagens' => ($resultado['alertas'] ?? []) ?: null,
                'data_autorizacao' => $data ?? now(),
            ]);

            return redirect()->route('nfse.show', $nfse)->with('success', 'NFS-e autorizada'.(filled($resultado['numero'] ?? null) ? ' com o número '.$resultado['numero'] : '').'.');
        }

        if ($status === 'rejeitada') {
            $this->repository->atualizar($nfse, [
                'situacao' => 'rejeitada',
                'codigo_status' => $codigo ? mb_substr((string) $codigo, 0, 10) : null,
                'motivo_status' => mb_substr($motivo, 0, 255),
                'mensagens' => ($resultado['mensagens'] ?? []) ?: null,
            ]);

            return redirect()->route('nfse.show', $nfse)->with('error', 'NFS-e rejeitada: '.$motivo.($codigo ? ' ('.$codigo.')' : ''));
        }

        // Sem resposta conclusiva: a nota continua pendente para não emitir outra DPS antes de consultar.
        $this->repository->atualizar($nfse, [
            'codigo_status' => $codigo ? mb_substr((string) $codigo, 0, 10) : null,
            'motivo_status' => mb_substr($motivo, 0, 255),
            'mensagens' => ($resultado['mensagens'] ?? []) ?: null,
        ]);

        return redirect()->route('nfse.show', $nfse)
            ->with('warning', 'Não foi possível confirmar a NFS-e na Sefin Nacional: '.$motivo.' Use "Consultar Sefin" antes de tentar de novo.');
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function registroDaNota(array $dados): array
    {
        return [
            'leiaute' => $dados['leiaute'],
            'c_trib_nac' => $dados['servico']['c_trib_nac'],
            'c_trib_mun' => $dados['servico']['c_trib_mun'] ?: null,
            'nbs' => $dados['servico']['nbs'] ?: null,
            'cst_ibs_cbs' => $dados['ibscbs']['cst'] ?: null,
            'class_trib' => $dados['ibscbs']['class_trib'] ?: null,
            'ind_op' => $dados['ibscbs']['ind_op'] ?: null,
            'municipio_prestacao' => $dados['servico']['municipio_prestacao'],
            'descricao' => trim($dados['servico']['descricao']),
            'valor_servico' => $dados['valores']['servico'],
            'valor_desconto' => $dados['valores']['desconto'],
            'valor_liquido' => $dados['valores']['liquido'],
            'iss_retido' => $dados['iss']['retido'],
            'aliquota_iss' => $dados['iss']['aliquota'],
            'valor_iss' => $dados['iss']['aliquota'] !== null
                ? round(($dados['valores']['servico'] - $dados['valores']['desconto']) * $dados['iss']['aliquota'] / 100, 2)
                : null,
            'data_competencia' => $dados['competencia']?->format('Y-m-d'),
            'data_emissao' => $dados['emissao'],
        ];
    }

    /**
     * Grava nos produtos de serviço os códigos usados, para a próxima NFS-e já vir preenchida,
     * e guarda no cliente se ele retém o ISSQN.
     *
     * @param  Collection<int, VendaItem>  $itens
     * @param  array<string, mixed>  $dados
     */
    private function memorizar(Venda $venda, Empresa $empresa, $itens, array $dados): void
    {
        $ids = $itens->pluck('produto_id')->filter()->unique()->map(fn ($id) => (int) $id)->values()->all();

        $this->repository->gravarCodigosNosProdutos((int) $empresa->id, $ids, [
            'nfse_c_trib_nac' => $dados['servico']['c_trib_nac'],
            'nfse_c_trib_mun' => $dados['servico']['c_trib_mun'] ?: null,
            'nfse_nbs' => $dados['servico']['nbs'] ?: null,
            'nfse_class_trib' => $dados['ibscbs']['class_trib'] ?: null,
            'nfse_ind_op' => $dados['ibscbs']['ind_op'] ?: null,
        ]);

        if ($venda->cliente_id) {
            $this->repository->gravarRetencaoNoCliente((int) $empresa->id, (int) $venda->cliente_id, (bool) $dados['iss']['retido']);
        }
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function tomador(Venda $venda): array
    {
        $cliente = $venda->cliente;
        if (! $cliente) {
            return [null, null];
        }

        $documento = NfseRegras::documentoCliente($cliente);
        if ($documento === null) {
            $informado = preg_replace('/\D/', '', (string) ($cliente->cnpj ?: $cliente->cpf)) ?? '';

            return [null, $informado !== '' ? 'O CPF/CNPJ do cliente está incompleto. Corrija o cadastro ou tire o cliente da venda.' : null];
        }

        [$endereco, $pendencia] = NfseRegras::enderecoTomador($cliente);

        return [[
            'tipo' => $documento[0],
            'documento' => $documento[1],
            'im' => trim((string) $cliente->inscricao_municipal),
            'nome' => trim((string) $cliente->nome),
            'endereco' => $endereco,
            'pendencia_endereco' => $pendencia,
            'fone' => NfseRegras::telefone($cliente->telefone ?: $cliente->celular),
            'email' => NfseRegras::email($cliente->email),
        ], null];
    }

    /**
     * @param  array{itens: Collection, total_itens: float, desconto: float, acrescimo: float, liquido: float}  $parte
     * @param  array<string, string>  $codigos
     * @return array<string, mixed>
     */
    private function entradaPadrao(Venda $venda, Empresa $empresa, array $parte, array $codigos, ?Nfse $anterior): array
    {
        $descricao = $this->descricaoDosItens($parte['itens']);

        $entrada = [
            'c_trib_nac' => $codigos['c_trib_nac'],
            'c_trib_mun' => $codigos['c_trib_mun'],
            'nbs' => $codigos['nbs'],
            'class_trib' => $codigos['class_trib'],
            'ind_op' => $codigos['ind_op'],
            'municipio_prestacao' => preg_replace('/\D/', '', (string) $empresa->cod_ibge) ?? '',
            'descricao' => $descricao,
            'iss_retido' => (bool) ($venda->cliente?->nfse_iss_retido ?? false),
            'aliquota_iss' => $empresa->nfse_aliquota_iss !== null ? (string) $empresa->nfse_aliquota_iss : '',
            'data_competencia' => ($venda->data_venda ?? now())->format('Y-m-d'),
            'memorizar' => true,
        ];

        // Nova tentativa depois de rejeição: volta com o que foi enviado da última vez.
        if ($anterior) {
            foreach (['c_trib_nac', 'c_trib_mun', 'nbs', 'class_trib', 'ind_op', 'municipio_prestacao', 'descricao'] as $campo) {
                if (filled($anterior->{$campo})) {
                    $entrada[$campo] = (string) $anterior->{$campo};
                }
            }
            $entrada['iss_retido'] = (bool) $anterior->iss_retido;
            if ($anterior->aliquota_iss !== null) {
                $entrada['aliquota_iss'] = (string) $anterior->aliquota_iss;
            }
        }

        return $entrada;
    }

    /**
     * Descrição padrão: um serviço por linha ("2 x Manutenção").
     *
     * @param  Collection<int, VendaItem>  $itens
     */
    private function descricaoDosItens(Collection $itens): string
    {
        return $itens->map(function (VendaItem $item) {
            $quantidade = rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',');

            return $quantidade.' x '.$item->produto_nome;
        })->implode("\n");
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function normalizarEntrada(array $entrada): array
    {
        $digitos = fn (string $campo) => preg_replace('/\D/', '', (string) ($entrada[$campo] ?? '')) ?? '';

        return [
            'c_trib_nac' => $digitos('c_trib_nac'),
            'c_trib_mun' => $digitos('c_trib_mun'),
            'nbs' => $digitos('nbs'),
            'class_trib' => $digitos('class_trib'),
            'ind_op' => $digitos('ind_op'),
            'municipio_prestacao' => $digitos('municipio_prestacao'),
            'descricao' => trim((string) ($entrada['descricao'] ?? '')),
            'iss_retido' => (bool) ($entrada['iss_retido'] ?? false),
            'aliquota_iss' => isset($entrada['aliquota_iss']) && $entrada['aliquota_iss'] !== '' ? str_replace(',', '.', (string) $entrada['aliquota_iss']) : null,
            'data_competencia' => (string) ($entrada['data_competencia'] ?? ''),
            'memorizar' => (bool) ($entrada['memorizar'] ?? false),
        ];
    }

    private function serie(Empresa $empresa): string
    {
        $serie = preg_replace('/\D/', '', (string) $empresa->nfse_serie) ?? '';

        return $serie === '' ? '1' : (string) (int) $serie;
    }

    private function autorizarNota(Nfse $nfse): void
    {
        if ((int) $nfse->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function autorizarVenda(Venda $venda): void
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
