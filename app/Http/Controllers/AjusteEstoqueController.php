<?php

namespace App\Http\Controllers;

use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AjusteEstoqueController extends Controller
{
    public function create()
    {
        return view('estoque.ajuste');
    }

    public function store(Request $request)
    {
        $quantidadeRaw = $request->input('quantidade');
        if (is_string($quantidadeRaw) || is_numeric($quantidadeRaw)) {
            $str = trim((string) $quantidadeRaw);
            if (str_contains($str, ',') && str_contains($str, '.')) {
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } elseif (str_contains($str, ',')) {
                $str = str_replace(',', '.', $str);
            }
            $request->merge(['quantidade' => round((float) $str, 3)]);
        }

        $tipoAjuste = $request->input('tipo') === 'ajuste';

        $request->validate([
            'produto_id'  => 'required|exists:produtos,id',
            'tipo'        => 'required|in:entrada,saida,ajuste',
            // Ajuste define o saldo absoluto (pode ser zero); entrada/saída exigem quantidade > 0
            'quantidade'  => $tipoAjuste ? 'required|numeric|min:0' : 'required|numeric|min:0.001',
            'motivo'      => 'required|string|max:200',
        ], [], [
            'produto_id' => 'produto',
            'tipo'       => 'tipo',
            'quantidade' => 'quantidade',
            'motivo'     => 'motivo',
        ]);

        $empresaId = (int) auth()->user()->empresa_id;

        $produto = Produto::where('id', $request->produto_id)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$produto) {
            abort(404);
        }

        if (!$produto->controla_estoque) {
            return back()
                ->withErrors(['produto_id' => 'Este produto não controla estoque.'])
                ->withInput();
        }

        $quantidade = (float) $request->quantidade;
        $tipo = $request->tipo;

        try {
            DB::transaction(function () use ($produto, $quantidade, $tipo, $request, $empresaId) {
                $produto = Produto::where('id', $produto->id)
                    ->lockForUpdate()
                    ->first();

                $estoqueAntes = (float) $produto->estoque_atual;

                if ($tipo === 'entrada') {
                    $produto->increment('estoque_atual', $quantidade);
                } elseif ($tipo === 'saida') {
                    if ($estoqueAntes < $quantidade) {
                        throw new \Exception(
                            'Estoque insuficiente para o produto "' . $produto->nome . '". Disponível: '
                            . number_format($estoqueAntes, 2, ',', '.')
                            . ', necessário: ' . number_format($quantidade, 2, ',', '.') . '.'
                        );
                    }
                    $produto->decrement('estoque_atual', $quantidade);
                } else {
                    $produto->update(['estoque_atual' => $quantidade]);
                }

                $produto->refresh();
                $estoqueDepois = (float) $produto->estoque_atual;

                MovimentacaoEstoque::create([
                    'empresa_id'     => $empresaId,
                    'produto_id'     => $produto->id,
                    'user_id'        => auth()->id(),
                    'tipo'           => $tipo,
                    'quantidade'     => $quantidade,
                    'estoque_antes'  => $estoqueAntes,
                    'estoque_depois' => $estoqueDepois,
                    'motivo'         => $request->motivo,
                    'origem'         => 'ajuste_manual',
                    'origem_id'      => null,
                ]);
            });
        } catch (\Exception $ex) {
            return back()
                ->with('error', $ex->getMessage())
                ->withInput();
        }

        return redirect()->route('estoque.historico')
            ->with('success', 'Ajuste de estoque registrado com sucesso.');
    }

    public function historico(Request $request)
    {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = MovimentacaoEstoque::with(['produto', 'usuario'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('produto_id')) {
            $query->where('produto_id', $request->produto_id);
        }

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->filled('data_inicio')) {
            $query->whereDate('created_at', '>=', $request->data_inicio);
        }

        if ($request->filled('data_fim')) {
            $query->whereDate('created_at', '<=', $request->data_fim);
        }

        $movimentacoes = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $produtoSelecionado = null;
        if ($request->filled('produto_id')) {
            $produtoSelecionado = Produto::where('empresa_id', $empresaId)
                ->find($request->produto_id);
        }

        return view('estoque.historico', [
            'movimentacoes'       => $movimentacoes,
            'produtoSelecionado'  => $produtoSelecionado,
            'filtros'             => $request->only(['produto_id', 'tipo', 'data_inicio', 'data_fim']),
        ]);
    }
}
