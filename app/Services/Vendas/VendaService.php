<?php

namespace App\Services\Vendas;

use App\Http\Requests\Vendas\VendaRequest;
use App\Impressao\CupomDocumento;
use App\Impressao\CupomPdf;
use App\Models\Clientes\Cliente;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Funcionarios\Funcionario;
use App\Models\Nfes\Nfe;
use App\Models\Orcamentos\Orcamento;
use App\Models\OrdensServico\OrdemServico;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Repositories\Vendas\VendaRepository;
use App\Services\Nfces\NfceService;
use App\Support\Auditoria;
use App\Support\MensagemPublica;
use App\Support\PrecoMinimo;
use App\Traits\ConversorMoeda;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class VendaService
{
    use ConversorMoeda;

    public function __construct(
        protected VendaRepository $repository
    ) {}

    public function index(Request $request)
    {
        $vendas = $this->repository->index($request);

        return view('venda.index', [
            'vendas' => $vendas,
            'filtros' => $request->only(['busca', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create()
    {
        return view('venda.create', [
            'formasPagamento' => $this->formasAtivas(),
        ]);
    }

    public function store(VendaRequest $request)
    {
        $chave = $this->chaveIdempotencia($request);
        $trava = $chave !== null ? $this->travarEnvio($chave) : null;

        if ($chave === null || $trava === null) {
            return $this->registrarVenda($request);
        }

        if ($trava === false) {
            return response()->json([
                'message' => 'Esta venda ainda está sendo registrada. Aguarde alguns segundos e confira a lista de vendas antes de tentar de novo.',
            ], 409);
        }

        // PDV: o mesmo envio repetido (rede caiu, tempo esgotado) devolve a venda já gravada em vez de lançar outra.
        try {
            $anterior = $this->vendaJaRegistrada($chave, $request);
            if ($anterior !== null) {
                return response()->json($this->respostaVendaRepetida($anterior));
            }

            return $this->registrarVenda($request, $chave);
        } finally {
            $trava->release();
        }
    }

    private function registrarVenda(VendaRequest $request, ?string $chave = null)
    {
        try {
            $resultado = $this->persistirVenda($request, $request->situacao ?? 'em_andamento');
        } catch (ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Dados inválidos.', 'errors' => $e->errors()], 422);
            }

            return back()->withErrors($e->errors())->withInput();
        }

        $venda = $resultado['venda'];
        $situacao = $resultado['situacao'];
        $avisoEstoque = $resultado['aviso_estoque'];
        $empresaId = (int) auth()->user()->empresa_id;

        if ($chave !== null) {
            $this->lembrarEnvio($chave, $request, $venda);
        }

        if ($request->expectsJson()) {
            $payload = [
                'id' => $venda->id,
                'numero' => $venda->numero,
                'situacao' => $venda->situacao,
            ];
            $empresa = Empresa::query()->find($empresaId);
            if ($situacao === 'confirmada' && $empresa?->nfce_emitir_pdv) {
                $payload['nfce'] = $this->emitirNfceDoPdv($request, $venda);
            }

            return response()->json($payload, 201);
        }

        if ($situacao === 'confirmada') {
            $mensagem = 'Venda #'.$venda->numero.' cadastrada e confirmada com sucesso.';
            if (ContaReceber::where('venda_id', $venda->id)->exists()) {
                $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
            } elseif (FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId)) {
                $mensagem .= ' Pagamento integral (à vista).';
            }

            $redirect = redirect()->route('venda.show', $venda)->with('success', $mensagem);
            if ($avisoEstoque) {
                $redirect = $redirect->with('warning', $avisoEstoque);
            }

            return $redirect;
        }

        $redirect = redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #'.$venda->numero.' cadastrada com sucesso.');
        if ($avisoEstoque) {
            $redirect = $redirect->with('warning', $avisoEstoque);
        }

        return $redirect;
    }

    /**
     * Cria e confirma uma venda, retornando o modelo pronto para emissão de NF-e.
     */
    public function criarVendaConfirmada(VendaRequest $request): Venda
    {
        $resultado = $this->persistirVenda($request, 'confirmada');

        return $resultado['venda']->load(['itens.produto.unidadeMedida', 'cliente']);
    }

    /**
     * Grava (ou atualiza) o rascunho de NF-e: venda em andamento, sem estoque, sem conta e sem confirmação automática.
     */
    public function salvarRascunhoNfe(VendaRequest $request, ?Venda $rascunho = null): Venda
    {
        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            throw ValidationException::withMessages(['itens' => 'Adicione pelo menos um item.']);
        }

        $empresaId = (int) auth()->user()->empresa_id;
        $totais = $this->calcularTotais($itens, $request->desconto, $request->acrescimo);

        $dados = [
            'cliente_id' => $request->cliente_id ?: null,
            'funcionario_id' => $this->resolverFuncionarioId($request->funcionario_id, $empresaId),
            'data_venda' => $request->data_venda,
            'data_saida' => $request->filled('data_saida') ? $request->data_saida : null,
            'subtotal' => $totais['subtotal'],
            'desconto' => $totais['desconto'],
            'acrescimo' => $totais['acrescimo'],
            'total' => $totais['total'],
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes' => $request->observacoes,
            'natureza_operacao' => $request->input('natureza_operacao') ?: 'VENDA DE MERCADORIA',
            'mod_frete' => (int) $request->input('mod_frete', 9),
            'ind_final' => $request->filled('ind_final') ? (int) $request->input('ind_final') : null,
            'inf_ad_fisco' => $request->input('inf_ad_fisco'),
        ];

        return DB::transaction(function () use ($rascunho, $dados, $itens, $empresaId) {
            if ($rascunho) {
                $rascunho = Venda::whereKey($rascunho->id)->lockForUpdate()->firstOrFail();

                if (! $rascunho->isRascunhoNfe()) {
                    throw ValidationException::withMessages(['itens' => 'Este rascunho já foi emitido ou cancelado.']);
                }

                $this->repository->update($rascunho, $dados, $itens);

                return $rascunho->fresh(['itens.produto.unidadeMedida', 'cliente']);
            }

            return $this->repository->store($dados + [
                'empresa_id' => $empresaId,
                'usuario_id' => auth()->id(),
                'numero' => $this->repository->proximoNumero($empresaId),
                'situacao' => 'em_andamento',
                'nfe_rascunho' => true,
            ], $itens)->load(['itens.produto.unidadeMedida', 'cliente']);
        });
    }

    /**
     * Confirma a venda do rascunho (estoque, conta a receber, comissão) para a emissão.
     */
    public function confirmarRascunhoNfe(Venda $rascunho): Venda
    {
        $venda = $this->repository->confirmar($rascunho->fresh('itens'));
        $venda->update(['nfe_rascunho' => false]);

        return $venda;
    }

    /**
     * @return array{venda: Venda, situacao: string, aviso_estoque: ?string}
     */
    /**
     * Grava a venda sem montar resposta HTTP (comandos). Mesmas regras da tela: estoque, preço mínimo,
     * conta a receber e comissão. Não emite NFC-e.
     *
     * @return array{venda: Venda, situacao: string, aviso_estoque: ?string}
     *
     * @throws ValidationException
     */
    public function gravar(VendaRequest $request): array
    {
        return $this->persistirVenda($request, $request->situacao ?? 'em_andamento');
    }

    protected function persistirVenda(VendaRequest $request, string $situacaoDesejada): array
    {
        // Criação e confirmação precisam ser atômicas: se a confirmação falhar, a venda não pode ficar gravada
        // (o usuário reenviaria o formulário e duplicaria a venda).
        return DB::transaction(fn () => $this->gravarVenda($request, $situacaoDesejada));
    }

    /**
     * @return array{venda: Venda, situacao: string, aviso_estoque: ?string}
     */
    private function gravarVenda(VendaRequest $request, string $situacaoDesejada): array
    {
        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            throw ValidationException::withMessages([
                'itens' => 'Adicione pelo menos um item.',
            ]);
        }

        $empresaId = (int) auth()->user()->empresa_id;
        $situacao = $situacaoDesejada;
        $totais = $this->calcularTotais($itens, $request->desconto, $request->acrescimo);
        PrecoMinimo::validar($itens, (float) $totais['desconto']);

        $parcelas = $this->parcelasInformadas($request, (float) $totais['total'], $empresaId);

        if (FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId) || $parcelas) {
            $situacao = 'confirmada';
        }

        $avisoEstoque = $this->montarAvisoEstoque($itens);
        $empresa = Empresa::query()->findOrFail($empresaId);

        if ($request->expectsJson() && $situacao === 'confirmada' && $empresa->nfce_emitir_pdv) {
            app(NfceService::class)->validarAntesDaVenda(
                $empresa,
                $itens,
                (float) $totais['total'],
                $this->documentoNfceInformado($request),
            );
        }

        $dados = [
            'empresa_id' => $empresaId,
            'cliente_id' => $request->cliente_id ?: null,
            'funcionario_id' => $this->resolverFuncionarioId($request->funcionario_id, $empresaId),
            'usuario_id' => auth()->id(),
            'numero' => $this->repository->proximoNumero($empresaId),
            'data_venda' => $request->data_venda,
            'data_entrega' => $request->data_entrega ?: null,
            'subtotal' => $totais['subtotal'],
            'desconto' => $totais['desconto'],
            'acrescimo' => $totais['acrescimo'],
            'total' => $totais['total'],
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes' => $request->observacoes,
            'natureza_operacao' => $request->input('natureza_operacao', 'VENDA DE MERCADORIA'),
            'mod_frete' => (int) $request->input('mod_frete', 9),
            'ind_final' => $request->filled('ind_final') ? (int) $request->input('ind_final') : null,
            'inf_ad_fisco' => $request->input('inf_ad_fisco'),
            'situacao' => $situacao === 'confirmada' ? 'em_andamento' : $situacao,
        ];

        $venda = $this->repository->store($dados, $itens);

        if ($situacao === 'confirmada') {
            try {
                $this->repository->confirmar($venda->load('itens'), $parcelas);
            } catch (\Exception $ex) {
                throw ValidationException::withMessages([
                    'itens' => MensagemPublica::de($ex),
                ]);
            }
        }

        return [
            'venda' => $venda->fresh(),
            'situacao' => $situacao,
            'aviso_estoque' => $avisoEstoque,
        ];
    }

    /**
     * @return array{ok: bool, situacao: ?string, mensagem: string, numero: ?string, chave: ?string, danfe_url: ?string}
     */
    private function emitirNfceDoPdv(VendaRequest $request, Venda $venda): array
    {
        try {
            $resultado = app(NfceService::class)->emitir(
                $venda,
                $this->documentoNfceInformado($request),
                (float) $request->input('valor_recebido', 0),
            );
        } catch (Throwable $e) {
            report($e);

            $resultado = [
                'ok' => false,
                'situacao' => null,
                'mensagem' => 'A venda foi registrada, mas a NFC-e não foi emitida.',
                'numero' => null,
                'chave' => null,
                'danfe_url' => null,
            ];
        }

        // A venda já está gravada: a NFC-e que não saiu é emitida depois, na tela da venda.
        // (A pendente já diz na mensagem para usar "Consultar SEFAZ" na tela da venda.)
        if (! $resultado['ok'] && ($resultado['situacao'] ?? null) !== 'pendente') {
            $resultado['dica'] = 'A venda está salva. Para emitir a NFC-e depois, abra "Ver venda".';
        }

        return $resultado;
    }

    private function documentoNfceInformado(VendaRequest $request): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $request->input('nfce_documento', '')) ?? '';
        if ($digitos !== '') {
            return $digitos;
        }

        if (! $request->filled('cliente_id')) {
            return null;
        }

        $cliente = Cliente::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->find($request->input('cliente_id'));
        if ($cliente === null) {
            return null;
        }

        $cnpj = preg_replace('/\D/', '', (string) $cliente->cnpj) ?? '';
        if (strlen($cnpj) === 14) {
            return $cnpj;
        }

        $cpf = preg_replace('/\D/', '', (string) $cliente->cpf) ?? '';

        return strlen($cpf) === 11 ? $cpf : null;
    }

    /**
     * Chave do envio do PDV. Só vale para resposta JSON; o formulário comum segue como antes.
     */
    private function chaveIdempotencia(VendaRequest $request): ?string
    {
        $token = trim((string) $request->input('chave_idempotencia', ''));

        if ($token === '' || ! $request->expectsJson()) {
            return null;
        }

        return 'venda-pdv:'.auth()->user()->empresa_id.':'.auth()->id().':'.$token;
    }

    /**
     * @return Lock|false|null false: outro envio com a mesma chave em andamento; null: cache indisponível, segue sem a proteção
     */
    private function travarEnvio(string $chave): Lock|false|null
    {
        try {
            // 120 s cobre o tempo máximo da requisição no PHP-FPM, inclusive a emissão da NFC-e.
            $trava = Cache::lock($chave.':trava', 120);

            return $trava->get() ? $trava : false;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function vendaJaRegistrada(string $chave, VendaRequest $request): ?Venda
    {
        $registro = Cache::get($chave);

        if (! is_array($registro) || ($registro['assinatura'] ?? null) !== $this->assinaturaEnvio($request)) {
            return null;
        }

        return Venda::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->find($registro['venda_id'] ?? 0);
    }

    private function lembrarEnvio(string $chave, VendaRequest $request, Venda $venda): void
    {
        try {
            Cache::put($chave, [
                'venda_id' => $venda->id,
                'assinatura' => $this->assinaturaEnvio($request),
            ], now()->addDay());
        } catch (Throwable $e) {
            // A venda já está gravada; a falha aqui só tira a proteção contra reenvio.
            report($e);
        }
    }

    /**
     * Mesmo carrinho e mesmo pagamento. Se o operador mudou algo antes de reenviar, é outra venda.
     */
    private function assinaturaEnvio(VendaRequest $request): string
    {
        return sha1((string) json_encode(
            $request->only(['cliente_id', 'forma_pagamento', 'desconto', 'acrescimo', 'itens', 'parcelas']),
            JSON_INVALID_UTF8_SUBSTITUTE
        ));
    }

    private function respostaVendaRepetida(Venda $venda): array
    {
        $payload = [
            'id' => $venda->id,
            'numero' => $venda->numero,
            'situacao' => $venda->situacao,
            'repetida' => true,
        ];

        $empresa = Empresa::query()->find($venda->empresa_id);
        if ($venda->situacao === 'confirmada' && $empresa?->nfce_emitir_pdv) {
            $payload['nfce'] = [
                'ok' => false,
                'situacao' => null,
                'mensagem' => 'Esta venda já tinha sido registrada. Confira a NFC-e na tela da venda.',
                'numero' => null,
                'chave' => null,
                'danfe_url' => null,
            ];
        }

        return $payload;
    }

    public function show(Venda $venda)
    {
        $this->autorizarVenda($venda);
        $this->ocultarCanceladaDoVendedor($venda);

        $venda->load([
            'cliente',
            'funcionario',
            'itens.produto',
            'usuario',
            'formaPagamento',
            'contasReceber' => fn ($q) => $q->orderBy('data_vencimento')->orderBy('id'),
            'nfes' => fn ($q) => $q->orderByDesc('id'),
        ]);
        $empresa = Empresa::query()->findOrFail($venda->empresa_id);

        // NFC-e e NF-e em quadros próprios: cada uma tem as rotas e a permissão dela.
        return view('venda.show', [
            'venda' => $venda,
            'empresa' => $empresa,
            'nfce' => $venda->nfes->first(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFCE),
            'nfeDaVenda' => $venda->nfes->first(fn (Nfe $nota) => (int) $nota->modelo === Nfe::MODELO_NFE),
            'nfceDisponivel' => app(NfceService::class)->configurada($empresa),
        ]);
    }

    public function pdf(Venda $venda)
    {
        $this->autorizarVenda($venda);
        $this->ocultarCanceladaDoVendedor($venda);

        $venda->load(['cliente', 'funcionario', 'itens.produto', 'usuario']);
        $pdf = Pdf::loadView('venda.pdf', ['venda' => $venda])->setPaper('a4', 'portrait');

        return $pdf->stream('venda_'.$venda->numero.'.pdf');
    }

    public function recibo(Venda $venda)
    {
        $this->autorizarVenda($venda);
        $this->ocultarCanceladaDoVendedor($venda);
        $venda->load(['cliente', 'itens', 'formaPagamento']);

        return CupomPdf::stream(CupomDocumento::venda($venda), 'venda_80mm_'.$venda->numero.'.pdf');
    }

    public function edit(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda cancelada não pode ser editada.');
        }

        if ($venda->situacao === 'confirmada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda confirmada não pode ser editada. Cancele a venda se precisar desfazer a operação.');
        }

        $venda->load(['itens.produto', 'cliente', 'funcionario']);

        return view('venda.edit', [
            'venda' => $venda,
            'formasPagamento' => $this->formasAtivas(),
        ]);
    }

    public function update(VendaRequest $request, Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda cancelada não pode ser editada.');
        }

        if ($venda->situacao === 'confirmada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda confirmada não pode ser editada. Cancele a venda se precisar desfazer a operação.');
        }

        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = (int) $venda->empresa_id;
        $situacao = $request->situacao ?? $venda->situacao;
        $pagamentoIntegral = FormaPagamento::pagamentoIntegral($request->forma_pagamento, $empresaId);
        $totais = $this->calcularTotais($itens, $request->desconto, $request->acrescimo);
        PrecoMinimo::validar($itens, (float) $totais['desconto']);
        $parcelas = $this->parcelasInformadas($request, (float) $totais['total'], $empresaId);

        // Venda já confirmada: não permite rebaixar situação pelo formulário
        if ($venda->situacao === 'confirmada') {
            $situacao = 'confirmada';
        }

        // Forma à vista = pagamento do valor completo → confirma automaticamente
        if (($pagamentoIntegral || $parcelas) && $venda->situacao !== 'confirmada') {
            $situacao = 'confirmada';
        }

        $jaConfirmada = $venda->situacao === 'confirmada';
        $deveConfirmar = $situacao === 'confirmada' && ! $jaConfirmada;
        $avisoEstoque = $this->montarAvisoEstoque($itens);

        $dados = [
            'cliente_id' => $request->cliente_id ?: null,
            'funcionario_id' => $this->resolverFuncionarioId($request->funcionario_id, $empresaId),
            'data_venda' => $request->data_venda,
            'data_entrega' => $request->data_entrega ?: null,
            'subtotal' => $totais['subtotal'],
            'desconto' => $totais['desconto'],
            'acrescimo' => $totais['acrescimo'],
            'total' => $totais['total'],
            'forma_pagamento' => $request->forma_pagamento,
            'observacoes' => $request->observacoes,
            'situacao' => $jaConfirmada ? 'confirmada' : ($deveConfirmar ? $venda->situacao : $situacao),
        ];

        try {
            DB::transaction(function () use ($venda, $dados, $itens, $deveConfirmar, $parcelas) {
                $this->repository->update($venda, $dados, $itens);

                if ($deveConfirmar) {
                    $this->repository->confirmar($venda->fresh('itens'), $parcelas);
                }
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $ex) {
            return redirect()->route('venda.show', $venda)
                ->with('error', MensagemPublica::de($ex));
        }

        $mensagem = 'Venda #'.$venda->numero.' atualizada com sucesso.';
        if ($deveConfirmar && $pagamentoIntegral) {
            $mensagem .= ' Confirmada automaticamente (pagamento integral).';
        } elseif ($deveConfirmar && ContaReceber::where('venda_id', $venda->id)->exists()) {
            $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
        }

        $redirect = redirect()->route('venda.show', $venda)->with('success', $mensagem);
        if ($avisoEstoque) {
            $redirect = $redirect->with('warning', $avisoEstoque);
        }

        return $redirect;
    }

    public function confirmar(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao !== 'em_andamento') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Somente vendas em andamento podem ser confirmadas.');
        }

        try {
            $this->repository->confirmar($venda->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('venda.show', $venda)
                ->with('error', MensagemPublica::de($ex));
        }

        $contaGerada = ContaReceber::where('venda_id', $venda->id)->exists();
        $mensagem = 'Venda #'.$venda->numero.' confirmada com sucesso.';

        if ($contaGerada) {
            $mensagem .= ' Conta a receber gerada automaticamente (venda a prazo).';
        }

        return redirect()->route('venda.show', $venda)
            ->with('success', $mensagem);
    }

    public function cancelar(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($bloqueio = $this->bloquearVendaFechadaParaVendedor($venda)) {
            return $bloqueio;
        }

        if ($venda->situacao === 'cancelada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda já está cancelada.');
        }

        $situacaoAnterior = $venda->situacao;
        $origens = $this->origensDaVenda($venda);

        try {
            $this->repository->cancelar($venda->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('venda.show', $venda)
                ->with('error', MensagemPublica::de($ex));
        }

        Auditoria::registrar('venda.cancelada', 'venda', $venda->id, [
            'numero' => $venda->numero,
            'situacao_anterior' => $situacaoAnterior,
            'total' => (float) $venda->total,
        ]);

        return redirect()->route('venda.show', $venda)
            ->with('success', 'Venda #'.$venda->numero.' cancelada.'.$this->avisoOrigensReabertas($origens, $venda));
    }

    public function destroy(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($bloqueio = $this->bloquearVendaFechadaParaVendedor($venda)) {
            return $bloqueio;
        }

        $numero = $venda->numero;
        $venda->load('itens');
        $origens = $this->origensDaVenda($venda);

        try {
            $this->repository->destroy($venda->id);
        } catch (\Exception $ex) {
            return redirect()->route('venda.show', $venda)
                ->with('error', MensagemPublica::de($ex));
        }

        Auditoria::registrar('venda.excluida', 'venda', $venda->id, [
            'numero' => $numero,
            'situacao' => $venda->situacao,
            'total' => (float) $venda->total,
        ]);

        return redirect()->route('venda.index')
            ->with('success', 'Venda #'.$numero.' excluída com sucesso.'.$this->avisoOrigensReabertas($origens, $venda));
    }

    /**
     * Só para a mensagem e a auditoria: quem reabre a OS, travada, é o repositório.
     */
    /**
     * @return array{os: ?OrdemServico, orcamento: ?Orcamento}
     */
    private function origensDaVenda(Venda $venda): array
    {
        return [
            'os' => OrdemServico::where('empresa_id', $venda->empresa_id)
                ->where('venda_id', $venda->id)
                ->where('situacao', 'faturada')
                ->first(['id', 'numero']),
            'orcamento' => Orcamento::where('empresa_id', $venda->empresa_id)
                ->where('venda_id', $venda->id)
                ->where('situacao', 'aprovado')
                ->first(['id', 'numero']),
        ];
    }

    /**
     * @param  array{os: ?OrdemServico, orcamento: ?Orcamento}  $origens
     */
    private function avisoOrigensReabertas(array $origens, Venda $venda): string
    {
        $aviso = '';
        $dadosVenda = ['venda_id' => $venda->id, 'venda_numero' => $venda->numero];

        $os = $origens['os'];
        if ($os && OrdemServico::whereKey($os->id)->value('situacao') === 'concluida') {
            Auditoria::registrar('ordem_servico.reaberta', 'ordem_servico', $os->id, ['numero' => $os->numero] + $dadosVenda);
            $aviso .= ' A OS #'.$os->numero.' voltou para "Concluída" e pode ser faturada de novo.';
        }

        $orcamento = $origens['orcamento'];
        if ($orcamento && Orcamento::whereKey($orcamento->id)->value('situacao') === 'pendente') {
            Auditoria::registrar('orcamento.reaberto', 'orcamento', $orcamento->id, ['numero' => $orcamento->numero] + $dadosVenda);
            $aviso .= ' O orçamento #'.$orcamento->numero.' voltou para "Pendente" e pode ser aprovado de novo.';
        }

        return $aviso;
    }

    /**
     * Avisa, no rascunho, que a confirmação será recusada. Quem efetiva a baixa é o repositório.
     */
    protected function montarAvisoEstoque(array $itens): ?string
    {
        $consumos = [];
        $produtos = Produto::query()->whereIn('id', array_column($itens, 'produto_id'))->get()->keyBy('id');
        foreach ($itens as $item) {
            if (empty($item['produto_id'])) {
                continue;
            }
            $produto = $produtos->get($item['produto_id']);
            if (! $produto || ! $produto->controla_estoque) {
                continue;
            }

            $consumos[$produto->id]['produto'] = $produto;
            $consumos[$produto->id]['quantidade'] = round(
                ($consumos[$produto->id]['quantidade'] ?? 0) + (float) $item['quantidade'],
                3
            );
        }

        $avisos = [];
        foreach ($consumos as $consumo) {
            $disponivel = round((float) $consumo['produto']->estoque_atual, 3);
            if (round($disponivel - $consumo['quantidade'], 3) < 0) {
                $avisos[] = '"'.$consumo['produto']->nome.'" (disponível '
                    .number_format($disponivel, 3, ',', '.').')';
            }
        }

        if ($avisos === []) {
            return null;
        }

        return 'Estoque insuficiente para '.implode(', ', $avisos)
            .'. A confirmação da venda será recusada enquanto o saldo não cobrir a quantidade.';
    }

    protected function processarItens(array $itens): array
    {
        $resultado = [];
        $ids = array_filter(array_map(
            static fn ($item) => is_array($item) && is_numeric($item['produto_id'] ?? null) ? (int) $item['produto_id'] : null,
            $itens,
        ));
        $produtos = Produto::with('unidadeMedida')->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($itens as $item) {
            if (empty($item['produto_id'])) {
                continue;
            }

            $produto = is_numeric($item['produto_id']) ? $produtos->get((int) $item['produto_id']) : null;

            if (! $produto) {
                continue;
            }
            // garantir que o produto pertence à mesma empresa do usuário
            if ((int) $produto->empresa_id !== (int) auth()->user()->empresa_id) {
                continue;
            }

            $quantidade = $this->parseQuantidade($item['quantidade'] ?? 0);
            if ($quantidade <= 0) {
                // ignorar itens com quantidade inválida (serão validados antes)
                continue;
            }
            $precoUnit = $this->parseMoeda($item['preco_unitario'] ?? 0);
            $descItem = $this->parseMoeda($item['desconto'] ?? 0);
            $total = $this->totalComDesconto($quantidade, $precoUnit, $descItem, $produto->nome);

            $somenteDigitos = static fn ($v) => preg_replace('/\D/', '', (string) $v) ?? '';
            $ncmDigitos = $somenteDigitos($item['ncm'] ?? $produto->ncm);
            $ncm = strlen($ncmDigitos) === 8 ? $ncmDigitos : '';
            $cstPis = $somenteDigitos($item['cst_pis'] ?? $produto->cst_pis);
            $cstCofins = $somenteDigitos($item['cst_cofins'] ?? $produto->cst_cofins);

            $resultado[] = [
                'produto_id' => $produto->id,
                'produto_nome' => $produto->nome,
                'produto_codigo' => $produto->codigo,
                'ncm' => $ncm !== '' ? str_pad($ncm, 8, '0', STR_PAD_LEFT) : null,
                'cest' => $somenteDigitos($item['cest'] ?? $produto->cest) ?: null,
                'cfop' => $somenteDigitos($item['cfop'] ?? $produto->cfop_padrao) ?: null,
                'unidade' => strtoupper(substr(trim((string) ($item['unidade'] ?? $produto->unidadeMedida?->sigla ?? 'UN')), 0, 6)),
                'origem' => (int) ($item['origem'] ?? $produto->origem ?? 0),
                'csosn' => $somenteDigitos($item['csosn'] ?? $produto->cst_icms) ?: null,
                'cst_pis' => $cstPis !== '' ? str_pad($cstPis, 2, '0', STR_PAD_LEFT) : null,
                'cst_cofins' => $cstCofins !== '' ? str_pad($cstCofins, 2, '0', STR_PAD_LEFT) : null,
                'ean' => $somenteDigitos($item['ean'] ?? $produto->codigo_barras) ?: null,
                'quantidade' => $quantidade,
                'preco_unitario' => $precoUnit,
                'desconto' => $descItem,
                'total' => $total,
            ];
        }

        return $resultado;
    }

    private function calcularTotais(array $itens, mixed $descontoRaw, mixed $acrescimoRaw): array
    {
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $desconto = $this->parseMoeda($descontoRaw);
        $acrescimo = $this->parseMoeda($acrescimoRaw);

        if ($desconto > $subtotal) {
            throw ValidationException::withMessages([
                'desconto' => 'O desconto não pode ser maior que o subtotal dos itens.',
            ]);
        }

        $total = max(0, round($subtotal - $desconto + $acrescimo, 2));

        return compact('subtotal', 'desconto', 'acrescimo', 'total');
    }

    private function ocultarCanceladaDoVendedor(Venda $venda): void
    {
        if (! auth()->user()->pode('vendas.ver_canceladas') && $venda->situacao === 'cancelada') {
            abort(403);
        }
    }

    private function bloquearVendaFechadaParaVendedor(Venda $venda): ?RedirectResponse
    {
        if (auth()->user()->pode('vendas.cancelar') || $venda->situacao === 'em_andamento') {
            return null;
        }

        if ($venda->situacao === 'confirmada') {
            return redirect()->route('venda.show', $venda)
                ->with('error', 'Venda fechada só pode ser consultada.');
        }

        abort(403);
    }

    private function autorizarVenda(Venda $venda): void
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function resolverFuncionarioId($funcionarioId, int $empresaId): ?int
    {
        if (! $funcionarioId) {
            return null;
        }

        $funcionario = Funcionario::where('id', $funcionarioId)
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->first();

        return $funcionario?->id;
    }

    private function formasAtivas()
    {
        return FormaPagamento::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true)
            ->orderBy('tipo')
            ->orderBy('nome')
            ->get();
    }

    /**
     * @return list<array{vencimento: string, valor: float, documento: string}>|null
     */
    private function parcelasInformadas(VendaRequest $request, float $total, int $empresaId): ?array
    {
        $brutas = $request->input('parcelas');
        if (! is_array($brutas) || $brutas === []) {
            return null;
        }

        $lista = [];
        $soma = 0.0;
        foreach (array_values($brutas) as $parcela) {
            if (! is_array($parcela)) {
                continue;
            }
            $valor = round((float) ($parcela['valor'] ?? 0), 2);
            $soma += $valor;
            $lista[] = [
                'vencimento' => (string) ($parcela['vencimento'] ?? ''),
                'valor' => $valor,
                'documento' => mb_substr(trim((string) ($parcela['documento'] ?? '')), 0, 40),
            ];
        }

        if ($lista === []) {
            return null;
        }

        $forma = FormaPagamento::buscarPorNome($request->forma_pagamento, $empresaId);
        if (! $forma || ! $forma->permiteParcelar()) {
            throw ValidationException::withMessages([
                'parcelas' => 'Forma à vista não recebe parcelamento.',
            ]);
        }

        if (abs($soma - round($total, 2)) > 0.05) {
            throw ValidationException::withMessages([
                'parcelas' => 'A soma das parcelas precisa fechar o total da venda.',
            ]);
        }

        // A tolerância cobre o arredondamento da tela; a diferença vai para a última parcela,
        // senão as contas a receber somariam alguns centavos a mais ou a menos que a venda.
        $diferenca = round(round($total, 2) - round($soma, 2), 2);
        if ($diferenca != 0.0) {
            $ultima = array_key_last($lista);
            $ajustada = round($lista[$ultima]['valor'] + $diferenca, 2);

            if ($ajustada <= 0) {
                throw ValidationException::withMessages([
                    'parcelas' => 'A soma das parcelas precisa fechar o total da venda.',
                ]);
            }

            $lista[$ultima]['valor'] = $ajustada;
        }

        return $lista;
    }
}
