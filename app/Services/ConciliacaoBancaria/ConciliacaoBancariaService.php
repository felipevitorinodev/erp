<?php

namespace App\Services\ConciliacaoBancaria;

use App\Models\ConciliacaoBancaria\ExtratoBancario;
use App\Models\ConciliacaoBancaria\TransacaoBancaria;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Repositories\ContasPagar\ContaPagarRepository;
use App\Repositories\ContasReceber\ContaReceberRepository;
use App\Support\Auditoria;
use App\Support\MensagemPublica;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ConciliacaoBancariaService
{
    public function __construct(
        protected OfxParser $parser,
        protected ContaReceberRepository $contasReceber,
        protected ContaPagarRepository $contasPagar,
    ) {}

    public function index()
    {
        $extratos = ExtratoBancario::where('empresa_id', auth()->user()->empresa_id)
            ->withCount('transacoes')
            ->orderByDesc('id')
            ->paginate(20);

        return view('conciliacao-bancaria.index', compact('extratos'));
    }

    public function importar(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|extensions:ofx|max:5120',
        ], [
            'arquivo.extensions' => 'Envie um arquivo com extensão .ofx.',
            'arquivo.max' => 'O arquivo OFX não pode exceder 5 MB.',
        ]);

        $arquivo = $request->file('arquivo');
        $conteudo = file_get_contents($arquivo->getRealPath());
        if ($conteudo === false) {
            throw ValidationException::withMessages(['arquivo' => 'Não foi possível ler o arquivo enviado.']);
        }

        $dados = $this->parser->parse($conteudo);
        $empresaId = (int) auth()->user()->empresa_id;
        $hash = hash('sha256', $conteudo);

        $repetidas = 0;
        $extrato = DB::transaction(function () use ($dados, $arquivo, $empresaId, $hash, &$repetidas) {
            if (ExtratoBancario::where('empresa_id', $empresaId)->where('arquivo_hash', $hash)->exists()) {
                throw ValidationException::withMessages([
                    'arquivo' => 'Este mesmo arquivo OFX já foi importado.',
                ]);
            }

            $extrato = ExtratoBancario::create([
                'empresa_id' => $empresaId,
                'arquivo_nome' => $arquivo->getClientOriginalName(),
                'arquivo_hash' => $hash,
                'conta_bancaria' => $dados['conta_bancaria'],
                'data_inicio' => $dados['data_inicio'],
                'data_fim' => $dados['data_fim'],
            ]);

            foreach ($dados['transacoes'] as $transacao) {
                // A mesma transação pode vir em dois extratos com períodos que se sobrepõem.
                if (TransacaoBancaria::where('empresa_id', $empresaId)
                    ->where('fingerprint', $transacao['fingerprint'])->exists()) {
                    $repetidas++;

                    continue;
                }

                $extrato->transacoes()->create(array_merge($transacao, [
                    'empresa_id' => $empresaId,
                    'situacao' => 'pendente',
                ]));
            }

            if ($repetidas === count($dados['transacoes'])) {
                throw ValidationException::withMessages([
                    'arquivo' => 'Todas as transações deste arquivo já foram importadas em outro extrato.',
                ]);
            }

            return $extrato;
        });

        $novas = count($dados['transacoes']) - $repetidas;
        Auditoria::registrar('conciliacao_bancaria.extrato_importado', 'extrato_bancario', $extrato->id, [
            'transacoes' => $novas,
            'repetidas' => $repetidas,
        ]);

        $mensagem = 'Extrato importado com '.$novas.' '.($novas === 1 ? 'transação' : 'transações').'.';
        if ($repetidas > 0) {
            $mensagem .= ' '.$repetidas.' já '.($repetidas === 1 ? 'tinha sido importada' : 'tinham sido importadas').' antes e '
                .($repetidas === 1 ? 'ficou' : 'ficaram').' de fora.';
        }

        return redirect()->route('conciliacao-bancaria.show', $extrato)
            ->with('success', $mensagem.' Revise as transações antes de conciliá-las.');
    }

    public function show(Request $request, ExtratoBancario $extrato)
    {
        $this->garantirEmpresa($extrato);
        $situacao = in_array($request->query('situacao'), ['pendente', 'conciliada', 'ignorada'], true)
            ? $request->query('situacao')
            : null;

        $transacoes = $extrato->transacoes()
            ->with(['contaReceber', 'contaPagar'])
            ->when($situacao, fn ($query) => $query->where('situacao', $situacao))
            ->orderBy('data_transacao')
            ->orderBy('id')
            ->paginate(50);

        $resumo = $extrato->transacoes()
            ->selectRaw('situacao, count(*) as quantidade')
            ->groupBy('situacao')
            ->pluck('quantidade', 'situacao');

        return view('conciliacao-bancaria.show', compact('extrato', 'transacoes', 'resumo'));
    }

    public function buscarContas(Request $request)
    {
        $dados = $request->validate([
            'tipo' => 'required|in:receber,pagar',
            'q' => 'nullable|string|max:100',
        ]);
        $empresaId = (int) auth()->user()->empresa_id;
        $termo = trim((string) ($dados['q'] ?? ''));
        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $query = $dados['tipo'] === 'receber'
            ? ContaReceber::with('cliente:id,nome')
            : ContaPagar::with('fornecedor:id,nome');

        $query->where('empresa_id', $empresaId)
            ->whereIn('situacao', ['aberta', 'parcial'])
            ->whereColumn('valor_pago', '<', 'valor')
            ->where(function ($builder) use ($dados, $termo) {
                $builder->where('descricao', 'like', '%'.$termo.'%');
                if (ctype_digit($termo)) {
                    $builder->orWhere('id', (int) $termo);
                }
                if ($dados['tipo'] === 'receber') {
                    $builder->orWhereHas('cliente', fn ($q) => $q->where('nome', 'like', '%'.$termo.'%'));
                } else {
                    $builder->orWhereHas('fornecedor', fn ($q) => $q->where('nome', 'like', '%'.$termo.'%'));
                }
            })
            ->orderBy('data_vencimento')
            ->limit(15);

        return $query->get()->map(function ($conta) use ($dados) {
            $relacionado = $dados['tipo'] === 'receber' ? $conta->cliente : $conta->fornecedor;
            $saldo = round((float) $conta->valor - (float) $conta->valor_pago, 2);

            return [
                'id' => $conta->id,
                'text' => '#'.$conta->id.' — '.$conta->descricao
                    .' — '.($relacionado->nome ?? 'Sem cadastro relacionado')
                    .' — saldo R$ '.number_format($saldo, 2, ',', '.'),
            ];
        });
    }

    public function conciliar(Request $request, TransacaoBancaria $transacao)
    {
        $this->garantirEmpresa($transacao);
        $dados = $request->validate([
            'conta_id' => 'required|integer|min:1',
        ]);

        try {
            DB::transaction(function () use ($transacao, $dados) {
                $transacao = TransacaoBancaria::where('empresa_id', auth()->user()->empresa_id)
                    ->lockForUpdate()->findOrFail($transacao->id);
                if ($transacao->situacao !== 'pendente' || (float) $transacao->valor === 0.0) {
                    throw new RuntimeException('Somente transações pendentes com valor diferente de zero podem ser conciliadas.');
                }

                $valor = abs((float) $transacao->valor);
                if ($transacao->valor > 0) {
                    $conta = ContaReceber::where('empresa_id', auth()->user()->empresa_id)->find($dados['conta_id']);
                    if (! $conta) {
                        throw new RuntimeException('A conta a receber selecionada não pertence a esta empresa.');
                    }
                    $this->validarSaldoConta($conta->situacao, (float) $conta->valor - (float) $conta->valor_pago, $valor);
                    $this->contasReceber->receber($conta, $valor, $transacao->data_transacao->toDateString(), 'Conciliação OFX');
                    $transacao->update(['conta_receber_id' => $conta->id]);
                } else {
                    $conta = ContaPagar::where('empresa_id', auth()->user()->empresa_id)->find($dados['conta_id']);
                    if (! $conta) {
                        throw new RuntimeException('A conta a pagar selecionada não pertence a esta empresa.');
                    }
                    $this->validarSaldoConta($conta->situacao, (float) $conta->valor - (float) $conta->valor_pago, $valor);
                    $this->contasPagar->pagar($conta, $valor, $transacao->data_transacao->toDateString(), 'Conciliação OFX');
                    $transacao->update(['conta_pagar_id' => $conta->id]);
                }

                $transacao->update(['situacao' => 'conciliada', 'conciliado_em' => now()]);
            });
        } catch (RuntimeException $exception) {
            return back()->with('error', MensagemPublica::de($exception));
        }

        Auditoria::registrar('conciliacao_bancaria.transacao_conciliada', 'transacao_bancaria', $transacao->id, [
            'valor' => (float) $transacao->valor,
            'conta_id' => (int) $dados['conta_id'],
        ]);

        return back()->with('success', 'Transação conciliada e baixa financeira registrada.');
    }

    public function ignorar(Request $request, TransacaoBancaria $transacao)
    {
        $this->garantirEmpresa($transacao);
        $dados = $request->validate(['motivo' => 'nullable|string|max:500']);

        $atualizada = TransacaoBancaria::where('empresa_id', auth()->user()->empresa_id)
            ->where('situacao', 'pendente')
            ->whereKey($transacao->id)
            ->update([
                'situacao' => 'ignorada',
                'motivo_ignorado' => trim((string) ($dados['motivo'] ?? '')) ?: null,
            ]);

        if (! $atualizada) {
            return back()->with('error', 'Somente transações pendentes podem ser ignoradas.');
        }

        Auditoria::registrar('conciliacao_bancaria.transacao_ignorada', 'transacao_bancaria', $transacao->id);

        return back()->with('success', 'Transação marcada como ignorada, sem criar lançamento financeiro.');
    }

    private function validarSaldoConta(string $situacao, float $saldo, float $valor): void
    {
        if (! in_array($situacao, ['aberta', 'parcial'], true) || $valor <= 0 || $valor > round($saldo, 2)) {
            throw new RuntimeException('A conta selecionada não está aberta ou não possui saldo suficiente para esta transação.');
        }
    }

    private function garantirEmpresa(ExtratoBancario|TransacaoBancaria $registro): void
    {
        if ((int) $registro->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
