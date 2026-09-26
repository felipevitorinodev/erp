<?php

namespace App\Services;

use App\Http\Requests\EntradaEstoqueRequest;
use App\Models\EntradaEstoque;
use App\Models\Fornecedor;
use App\Models\Produto;
use App\Repositories\EntradaEstoqueRepository;
use App\Traits\ConversorMoeda;
use Illuminate\Http\Request;

class EntradaEstoqueService
{
    use ConversorMoeda;

    public function __construct(
        protected EntradaEstoqueRepository $repository
    ) {}

    public function index(Request $request)
    {
        $entradas = $this->repository->index($request);

        return view('entrada-estoque.index', [
            'entradas' => $entradas,
            'filtros'  => $request->only(['busca', 'situacao', 'data_inicio', 'data_fim']),
        ]);
    }

    public function create()
    {
        return view('entrada-estoque.create');
    }

    public function store(EntradaEstoqueRequest $request)
    {
        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = auth()->user()->empresa_id;
        $fornecedorId = $request->fornecedor_id ?: null;

        if ($fornecedorId) {
            $fornecedor = Fornecedor::where('id', $fornecedorId)
                ->where('empresa_id', $empresaId)
                ->first();

            if (!$fornecedor) {
                return back()
                    ->withErrors(['fornecedor_id' => 'Fornecedor inválido para esta empresa.'])
                    ->withInput();
            }
        }

        $desconto = $this->parseMoeda($request->desconto);
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $total = max(0, round($subtotal - $desconto, 2));

        $dados = [
            'empresa_id'    => $empresaId,
            'fornecedor_id' => $fornecedorId,
            'numero'        => trim((string) $request->numero),
            'data_entrada'  => $request->data_entrada,
            'subtotal'      => $subtotal,
            'desconto'      => $desconto,
            'total'         => $total,
            'observacoes'   => $request->observacoes,
            'situacao'      => 'rascunho',
        ];

        $entrada = $this->repository->store($dados, $itens);

        return redirect()->route('entrada-estoque.show', $entrada)
            ->with('success', 'Entrada de estoque #' . $entrada->numero . ' cadastrada com sucesso.');
    }

    public function show(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);
        $entradaEstoque->load(['fornecedor', 'itens.produto']);

        return view('entrada-estoque.show', ['entrada' => $entradaEstoque]);
    }

    public function edit(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'rascunho') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas em rascunho podem ser editadas.');
        }

        $entradaEstoque->load(['fornecedor', 'itens.produto']);

        return view('entrada-estoque.edit', ['entrada' => $entradaEstoque]);
    }

    public function update(EntradaEstoqueRequest $request, EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'rascunho') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas em rascunho podem ser editadas.');
        }

        $itens = $this->processarItens($request->itens ?? []);

        if (empty($itens)) {
            return back()
                ->withErrors(['itens' => 'Adicione pelo menos um item.'])
                ->withInput();
        }

        $empresaId = (int) $entradaEstoque->empresa_id;
        $fornecedorId = $request->fornecedor_id ?: null;

        if ($fornecedorId) {
            $fornecedor = Fornecedor::where('id', $fornecedorId)
                ->where('empresa_id', $empresaId)
                ->first();

            if (!$fornecedor) {
                return back()
                    ->withErrors(['fornecedor_id' => 'Fornecedor inválido para esta empresa.'])
                    ->withInput();
            }
        }

        $desconto = $this->parseMoeda($request->desconto);
        $subtotal = round(array_sum(array_column($itens, 'total')), 2);
        $total = max(0, round($subtotal - $desconto, 2));

        $dados = [
            'fornecedor_id' => $fornecedorId,
            'numero'        => trim((string) $request->numero),
            'data_entrada'  => $request->data_entrada,
            'subtotal'      => $subtotal,
            'desconto'      => $desconto,
            'total'         => $total,
            'observacoes'   => $request->observacoes,
            'situacao'      => 'rascunho',
        ];

        $this->repository->update($entradaEstoque, $dados, $itens);

        return redirect()->route('entrada-estoque.show', $entradaEstoque)
            ->with('success', 'Entrada de estoque #' . $entradaEstoque->numero . ' atualizada com sucesso.');
    }

    public function confirmar(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'rascunho') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas em rascunho podem ser confirmadas.');
        }

        try {
            $this->repository->confirmar($entradaEstoque->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('entrada-estoque.show', $entradaEstoque)
            ->with('success', 'Entrada #' . $entradaEstoque->numero . ' confirmada. Estoque atualizado e conta a pagar gerada.');
    }

    public function cancelar(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        if ($entradaEstoque->situacao !== 'confirmada') {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', 'Somente entradas confirmadas podem ser canceladas.');
        }

        try {
            $this->repository->cancelar($entradaEstoque->load('itens'));
        } catch (\Exception $ex) {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('entrada-estoque.show', $entradaEstoque)
            ->with('success', 'Entrada #' . $entradaEstoque->numero . ' cancelada. Estoque e conta a pagar revertidos.');
    }

    public function destroy(EntradaEstoque $entradaEstoque)
    {
        $this->autorizarEntrada($entradaEstoque);

        $numero = $entradaEstoque->numero;

        try {
            $this->repository->destroy($entradaEstoque->id);
        } catch (\Exception $ex) {
            return redirect()->route('entrada-estoque.show', $entradaEstoque)
                ->with('error', $ex->getMessage());
        }

        return redirect()->route('entrada-estoque.index')
            ->with('success', 'Entrada #' . $numero . ' excluída com sucesso.');
    }

    private function autorizarEntrada(EntradaEstoque $entrada): void
    {
        if ((int) $entrada->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function processarItens(array $itens): array
    {
        $resultado = [];

        foreach ($itens as $item) {
            if (empty($item['produto_id'])) {
                continue;
            }

            $produto = Produto::find($item['produto_id']);

            if (!$produto) {
                continue;
            }

            if ((int) $produto->empresa_id !== (int) auth()->user()->empresa_id) {
                continue;
            }

            $quantidade = $this->parseQuantidade($item['quantidade'] ?? 0);
            if ($quantidade <= 0) {
                continue;
            }

            $precoUnit = $this->parseMoeda($item['preco_unitario'] ?? 0);
            $descItem  = $this->parseMoeda($item['desconto'] ?? 0);
            $total     = max(0, ($quantidade * $precoUnit) - $descItem);

            $resultado[] = [
                'produto_id'     => $produto->id,
                'produto_nome'   => $produto->nome,
                'produto_codigo' => $produto->codigo,
                'quantidade'     => $quantidade,
                'preco_unitario' => $precoUnit,
                'desconto'       => $descItem,
                'total'          => $total,
            ];
        }

        return $resultado;
    }
}
