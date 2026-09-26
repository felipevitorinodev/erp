<?php

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Fornecedor;
use App\Models\Funcionario;
use App\Models\Grupo;
use App\Models\Produto;
use App\Models\UnidadeMedida;
use App\Models\Venda;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    Route::get('/api/clientes/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = Cliente::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ($q !== '') {
            $query->where('nome', 'like', '%' . $q . '%');
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'cpf', 'cnpj')
            ->limit($limit)
            ->get();
    })->name('api.clientes.busca');

    Route::get('/api/funcionarios/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = Funcionario::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ($q !== '') {
            $query->where('nome', 'like', '%' . $q . '%');
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'percentual_comissao')
            ->limit($limit)
            ->get();
    })->name('api.funcionarios.busca');

    Route::get('/api/fornecedores/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = Fornecedor::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ($q !== '') {
            $query->where('nome', 'like', '%' . $q . '%');
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'cpf', 'cnpj')
            ->limit($limit)
            ->get();
    })->name('api.fornecedores.busca');

    Route::get('/api/produtos/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = Produto::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ((string) request('apenas_controlados') === '1') {
            $query->where('controla_estoque', true);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('nome', 'like', '%' . $q . '%')
                    ->orWhere('codigo', 'like', '%' . $q . '%');
            });
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'codigo', 'preco_venda', 'preco_custo', 'estoque_atual')
            ->limit($limit)
            ->get();
    })->name('api.produtos.busca');

    Route::get('/api/grupos/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = Grupo::with('parent:id,nome')
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ($q !== '') {
            $query->where('nome', 'like', '%' . $q . '%');
        }

        if (request()->boolean('apenas_principais')) {
            $query->whereNull('parent_id');
        }

        if (request()->filled('exclude')) {
            $query->where('id', '!=', request('exclude'));
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'parent_id')
            ->limit($limit)
            ->get();
    })->name('api.grupos.busca');

    Route::get('/api/unidades-medida/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = UnidadeMedida::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('nome', 'like', '%' . $q . '%')
                    ->orWhere('sigla', 'like', '%' . $q . '%');
            });
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'sigla')
            ->limit($limit)
            ->get();
    })->name('api.unidades-medida.busca');

    Route::get('/api/empresas/busca', function () {
        if ((auth()->user()->perfil ?? '') !== 'admin') {
            abort(403);
        }

        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = Empresa::query();

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('razao_social', 'like', '%' . $q . '%')
                    ->orWhere('nome_fantasia', 'like', '%' . $q . '%')
                    ->orWhere('cnpj', 'like', '%' . $q . '%');
            });
        }

        return $query->orderBy('razao_social')
            ->select('id', 'razao_social', 'nome_fantasia', 'cnpj')
            ->limit($limit)
            ->get();
    })->middleware('perfil:admin')->name('api.empresas.busca');

    Route::get('/api/vendas/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;
        $empresaId = auth()->user()->empresa_id;

        $query = Venda::with('cliente:id,nome')
            ->where('empresa_id', $empresaId);

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('numero', 'like', '%' . $q . '%')
                    ->orWhereHas('cliente', function ($c) use ($q) {
                        $c->where('nome', 'like', '%' . $q . '%');
                    });
            });
        }

        if (request()->filled('cliente_id')) {
            $query->where('cliente_id', request('cliente_id'));
        }

        return $query->orderBy('numero')
            ->select('id', 'numero', 'data_venda', 'total', 'cliente_id', 'situacao')
            ->limit($limit)
            ->get();
    })->name('api.vendas.busca');

    Route::get('/api/formas-pagamento/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = FormaPagamento::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ($q !== '') {
            $query->where('nome', 'like', '%' . $q . '%');
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'tipo', 'gera_conta_receber', 'dias_vencimento')
            ->limit($limit)
            ->get();
    })->name('api.formas-pagamento.busca');

    Route::get('/api/cliente/{cliente}/vendas', function (Cliente $cliente) {
        if ((int) $cliente->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }

        return Venda::where('empresa_id', auth()->user()->empresa_id)
            ->where('cliente_id', $cliente->id)
            ->orderByDesc('id')
            ->select('id', 'numero', 'data_venda', 'total', 'situacao')
            ->limit(50)
            ->get();
    })->name('api.cliente.vendas');

});
