<?php

namespace App\Http\Controllers\Relatorios;

use App\Http\Controllers\Controller;
use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Models\Clientes\Cliente;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Funcionarios\Funcionario;
use App\Models\Grupos\Grupo;
use App\Models\Produtos\Produto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class OpcaoRelatorioController extends Controller
{
    public function clientes(): JsonResponse
    {
        return $this->pessoas(Cliente::query());
    }

    public function fornecedores(): JsonResponse
    {
        return $this->pessoas(Fornecedor::query());
    }

    public function funcionarios(): JsonResponse
    {
        return $this->pessoas(Funcionario::query());
    }

    public function formasPagamento(): JsonResponse
    {
        return $this->pessoas(FormaPagamento::query()->where('ativo', true));
    }

    public function grupos(): JsonResponse
    {
        return $this->pessoas(Grupo::query()->where('ativo', true));
    }

    public function produtos(): JsonResponse
    {
        $termo = $this->termo();
        $empresaId = (int) auth()->user()->empresa_id;

        $produtos = Produto::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->when($termo !== '', function (Builder $query) use ($termo) {
                $query->where(function (Builder $filtro) use ($termo) {
                    $filtro->where('nome', 'like', "%{$termo}%")
                        ->orWhere('codigo', 'like', "%{$termo}%");
                });
            })
            ->orderBy('nome')
            ->limit($this->limite($termo))
            ->get(['id', 'nome', 'codigo']);

        return response()->json($produtos->map(fn (Produto $produto) => [
            'id' => $produto->id,
            'nome' => $produto->nome,
            'codigo' => $produto->codigo,
        ])->values());
    }

    public function categorias(): JsonResponse
    {
        $tipo = request()->validate([
            'tipo' => ['nullable', 'in:receita,despesa'],
        ])['tipo'] ?? null;

        $termo = $this->termo();

        $categorias = CategoriaFinanceira::query()
            ->where('empresa_id', (int) auth()->user()->empresa_id)
            ->where('ativo', true)
            ->when($tipo, fn (Builder $query) => $query->where('tipo', $tipo))
            ->when($termo !== '', fn (Builder $query) => $query->where('nome', 'like', "%{$termo}%"))
            ->orderBy('nome')
            ->limit($this->limite($termo))
            ->get(['id', 'nome']);

        return response()->json($categorias->map(fn (CategoriaFinanceira $categoria) => [
            'id' => $categoria->id,
            'nome' => $categoria->nome,
        ])->values());
    }

    private function pessoas(Builder $query): JsonResponse
    {
        $termo = $this->termo();

        $linhas = $query
            ->where('empresa_id', (int) auth()->user()->empresa_id)
            ->when($termo !== '', fn (Builder $filtro) => $filtro->where('nome', 'like', "%{$termo}%"))
            ->orderBy('nome')
            ->limit($this->limite($termo))
            ->get(['id', 'nome']);

        return response()->json($linhas->map(fn ($linha) => [
            'id' => $linha->id,
            'nome' => $linha->nome,
        ])->values());
    }

    private function termo(): string
    {
        return str_replace(['%', '_'], '', trim((string) request()->query('q', '')));
    }

    private function limite(string $termo): int
    {
        return $termo === '' ? 8 : 20;
    }
}
