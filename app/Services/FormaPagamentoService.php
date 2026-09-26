<?php

namespace App\Services;

use App\Models\FormaPagamento;
use App\Repositories\FormaPagamentoRepository;
use Illuminate\Http\Request;

class FormaPagamentoService
{
    public function __construct(
        protected FormaPagamentoRepository $repository
    ) {}

    public function index(Request $request)
    {
        $formasPagamento = $this->repository->index($request);

        return view('formaPagamento.index', [
            'formasPagamento' => $formasPagamento,
            'filtros'         => $request->only(['busca']),
        ]);
    }

    public function create()
    {
        return view('formaPagamento.create');
    }

    public function store($request)
    {
        $this->repository->store($request->validated());

        return redirect()->route('formaPagamento.index')
            ->with('success', 'Forma de pagamento cadastrada com sucesso.');
    }

    public function edit(FormaPagamento $formaPagamento)
    {
        if ((int) $formaPagamento->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        return view('formaPagamento.edit', ['formaPagamento' => $formaPagamento]);
    }

    public function update($request, FormaPagamento $formaPagamento)
    {
        if ((int) $formaPagamento->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        $this->repository->update($formaPagamento, $request->validated());

        return redirect()
            ->route('formaPagamento.index')
            ->with('success', 'Forma de pagamento editada com sucesso.');
    }

    public function destroy($id)
    {
        $this->repository->destroy($id);

        return redirect()->route('formaPagamento.index')
            ->with('success', 'Forma de pagamento apagada com sucesso.');
    }
}
