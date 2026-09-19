<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ContaPagarController;
use App\Http\Controllers\ContaReceberController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\FormaPagamentoController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\OrcamentoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\UnidadeMedidaController;
use App\Http\Controllers\VendaController;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Fornecedor;
use App\Models\Grupo;
use App\Models\Produto;
use App\Models\UnidadeMedida;
use App\Models\Venda;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        return view('welcome');
    });

    Route::prefix('empresa')->group(function () {
        Route::get('/', [EmpresaController::class, 'index'])->name('empresa.index');
        Route::get('/novo', [EmpresaController::class, 'create'])->name('empresa.create');
        Route::post('/salvar', [EmpresaController::class, 'store'])->name('empresa.store');
        Route::get('{empresa}/editar', [EmpresaController::class, 'edit'])->name('empresa.edit');
        Route::put('{empresa}/editar/salvar', [EmpresaController::class, 'update'])->name('empresa.update');
        Route::delete('{empresa}/excluir', [EmpresaController::class, 'destroy'])->name('empresa.destroy');
    });

    Route::prefix('cliente')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('cliente.index');
        Route::get('/novo', [ClienteController::class, 'create'])->name('cliente.create');
        Route::post('/salvar', [ClienteController::class, 'store'])->name('cliente.store');
        Route::get('{cliente}/editar', [ClienteController::class, 'edit'])->name('cliente.edit');
        Route::put('{cliente}/editar/salvar', [ClienteController::class, 'update'])->name('cliente.update');
        Route::delete('{cliente}/excluir', [ClienteController::class, 'destroy'])->name('cliente.destroy');
    });

    Route::prefix('fornecedor')->group(function () {
        Route::get('/', [FornecedorController::class, 'index'])->name('fornecedor.index');
        Route::get('/novo', [FornecedorController::class, 'create'])->name('fornecedor.create');
        Route::post('/salvar', [FornecedorController::class, 'store'])->name('fornecedor.store');
        Route::get('{fornecedor}/editar', [FornecedorController::class, 'edit'])->name('fornecedor.edit');
        Route::put('{fornecedor}/editar/salvar', [FornecedorController::class, 'update'])->name('fornecedor.update');
        Route::delete('{fornecedor}/excluir', [FornecedorController::class, 'destroy'])->name('fornecedor.destroy');
    });

    Route::prefix('funcionario')->group(function () {
        Route::get('/', [FuncionarioController::class, 'index'])->name('funcionario.index');
        Route::get('/novo', [FuncionarioController::class, 'create'])->name('funcionario.create');
        Route::post('/salvar', [FuncionarioController::class, 'store'])->name('funcionario.store');
        Route::get('{funcionario}/editar', [FuncionarioController::class, 'edit'])->name('funcionario.edit');
        Route::put('{funcionario}/editar/salvar', [FuncionarioController::class, 'update'])->name('funcionario.update');
        Route::delete('{funcionario}/excluir', [FuncionarioController::class, 'destroy'])->name('funcionario.destroy');
    });

    Route::prefix('grupo')->group(function () {
        Route::get('/', [GrupoController::class, 'index'])->name('grupo.index');
        Route::get('/novo', [GrupoController::class, 'create'])->name('grupo.create');
        Route::post('/salvar', [GrupoController::class, 'store'])->name('grupo.store');
        Route::get('{grupo}/editar', [GrupoController::class, 'edit'])->name('grupo.edit');
        Route::put('{grupo}/editar/salvar', [GrupoController::class, 'update'])->name('grupo.update');
        Route::delete('{grupo}/excluir', [GrupoController::class, 'destroy'])->name('grupo.destroy');
    });

    Route::prefix('unidadeMedida')->group(function () {
        Route::get('/', [UnidadeMedidaController::class, 'index'])->name('unidadeMedida.index');
        Route::get('/novo', [UnidadeMedidaController::class, 'create'])->name('unidadeMedida.create');
        Route::post('/salvar', [UnidadeMedidaController::class, 'store'])->name('unidadeMedida.store');
        Route::get('{unidadeMedida}/editar', [UnidadeMedidaController::class, 'edit'])->name('unidadeMedida.edit');
        Route::put('{unidadeMedida}/editar/salvar', [UnidadeMedidaController::class, 'update'])->name('unidadeMedida.update');
        Route::delete('{unidadeMedida}/excluir', [UnidadeMedidaController::class, 'destroy'])->name('unidadeMedida.destroy');
    });

    Route::prefix('formaPagamento')->group(function () {
        Route::get('/', [FormaPagamentoController::class, 'index'])->name('formaPagamento.index');
        Route::get('/novo', [FormaPagamentoController::class, 'create'])->name('formaPagamento.create');
        Route::post('/salvar', [FormaPagamentoController::class, 'store'])->name('formaPagamento.store');
        Route::get('{formaPagamento}/editar', [FormaPagamentoController::class, 'edit'])->name('formaPagamento.edit');
        Route::put('{formaPagamento}/editar/salvar', [FormaPagamentoController::class, 'update'])->name('formaPagamento.update');
        Route::delete('{formaPagamento}/excluir', [FormaPagamentoController::class, 'destroy'])->name('formaPagamento.destroy');
    });

    Route::prefix('produto')->group(function () {
        Route::get('/', [ProdutoController::class, 'index'])->name('produto.index');
        Route::get('/novo', [ProdutoController::class, 'create'])->name('produto.create');
        Route::post('/salvar', [ProdutoController::class, 'store'])->name('produto.store');
        Route::get('{produto}/editar', [ProdutoController::class, 'edit'])->name('produto.edit');
        Route::put('{produto}/editar/salvar', [ProdutoController::class, 'update'])->name('produto.update');
        Route::delete('{produto}/excluir', [ProdutoController::class, 'destroy'])->name('produto.destroy');
    });

    Route::prefix('venda')->group(function () {
        Route::get('/', [VendaController::class, 'index'])->name('venda.index');
        Route::get('/nova', [VendaController::class, 'create'])->name('venda.create');
        Route::post('/salvar', [VendaController::class, 'store'])->name('venda.store');
        Route::get('{venda}/ver', [VendaController::class, 'show'])->name('venda.show');
        Route::get('{venda}/editar', [VendaController::class, 'edit'])->name('venda.edit');
        Route::put('{venda}/editar/salvar', [VendaController::class, 'update'])->name('venda.update');
        Route::post('{venda}/confirmar', [VendaController::class, 'confirmar'])->name('venda.confirmar');
        Route::post('{venda}/cancelar', [VendaController::class, 'cancelar'])->name('venda.cancelar');
        Route::delete('{venda}/excluir', [VendaController::class, 'destroy'])->name('venda.destroy');
    });

    Route::prefix('orcamento')->group(function () {
        Route::get('/', [OrcamentoController::class, 'index'])->name('orcamento.index');
        Route::get('/novo', [OrcamentoController::class, 'create'])->name('orcamento.create');
        Route::post('/salvar', [OrcamentoController::class, 'store'])->name('orcamento.store');
        Route::get('{orcamento}/ver', [OrcamentoController::class, 'show'])->name('orcamento.show');
        Route::get('{orcamento}/editar', [OrcamentoController::class, 'edit'])->name('orcamento.edit');
        Route::put('{orcamento}/editar/salvar', [OrcamentoController::class, 'update'])->name('orcamento.update');
        Route::post('{orcamento}/aprovar', [OrcamentoController::class, 'aprovar'])->name('orcamento.aprovar');
        Route::post('{orcamento}/cancelar', [OrcamentoController::class, 'cancelar'])->name('orcamento.cancelar');
        Route::delete('{orcamento}/excluir', [OrcamentoController::class, 'destroy'])->name('orcamento.destroy');
    });

    Route::prefix('conta-receber')->group(function () {
        Route::get('/', [ContaReceberController::class, 'index'])->name('conta-receber.index');
        Route::get('/nova', [ContaReceberController::class, 'create'])->name('conta-receber.create');
        Route::post('/salvar', [ContaReceberController::class, 'store'])->name('conta-receber.store');
        Route::get('{contaReceber}/ver', [ContaReceberController::class, 'show'])->name('conta-receber.show');
        Route::get('{contaReceber}/editar', [ContaReceberController::class, 'edit'])->name('conta-receber.edit');
        Route::put('{contaReceber}/editar/salvar', [ContaReceberController::class, 'update'])->name('conta-receber.update');
        Route::post('{contaReceber}/receber', [ContaReceberController::class, 'receber'])->name('conta-receber.receber');
        Route::post('{contaReceber}/cancelar', [ContaReceberController::class, 'cancelar'])->name('conta-receber.cancelar');
        Route::delete('{contaReceber}/excluir', [ContaReceberController::class, 'destroy'])->name('conta-receber.destroy');
    });

    Route::prefix('conta-pagar')->group(function () {
        Route::get('/', [ContaPagarController::class, 'index'])->name('conta-pagar.index');
        Route::get('/nova', [ContaPagarController::class, 'create'])->name('conta-pagar.create');
        Route::post('/salvar', [ContaPagarController::class, 'store'])->name('conta-pagar.store');
        Route::get('{contaPagar}/ver', [ContaPagarController::class, 'show'])->name('conta-pagar.show');
        Route::get('{contaPagar}/editar', [ContaPagarController::class, 'edit'])->name('conta-pagar.edit');
        Route::put('{contaPagar}/editar/salvar', [ContaPagarController::class, 'update'])->name('conta-pagar.update');
        Route::post('{contaPagar}/pagar', [ContaPagarController::class, 'pagar'])->name('conta-pagar.pagar');
        Route::post('{contaPagar}/cancelar', [ContaPagarController::class, 'cancelar'])->name('conta-pagar.cancelar');
        Route::delete('{contaPagar}/excluir', [ContaPagarController::class, 'destroy'])->name('conta-pagar.destroy');
    });

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

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('nome', 'like', '%' . $q . '%')
                    ->orWhere('codigo', 'like', '%' . $q . '%');
            });
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'codigo', 'preco_venda')
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
    })->name('api.empresas.busca');

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
