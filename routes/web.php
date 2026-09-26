<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaFinanceiraController;
use App\Http\Controllers\AjusteEstoqueController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComissaoController;
use App\Http\Controllers\ContaPagarController;
use App\Http\Controllers\ContaReceberController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\EntradaEstoqueController;
use App\Http\Controllers\FluxoCaixaController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\FormaPagamentoController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\OrcamentoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\UnidadeMedidaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VendaController;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::get('/esqueci-senha', [AuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/esqueci-senha', [AuthController::class, 'sendResetLink'])->name('password.email');
Route::get('/redefinir-senha/{token}', [AuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/redefinir-senha', [AuthController::class, 'resetPassword'])->name('password.update');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {

    Route::get('/', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;
        $hoje = now()->toDateString();
        $em7Dias = now()->addDays(7)->toDateString();

        $vendasHoje = Venda::where('empresa_id', $empresaId)
            ->whereDate('data_venda', $hoje)
            ->count();

        $faturamentoHoje = (float) Venda::where('empresa_id', $empresaId)
            ->where('situacao', 'confirmada')
            ->whereDate('data_venda', $hoje)
            ->sum('total');

        $contasReceberVencidas = ContaReceber::where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '<', $hoje)
            ->whereIn('situacao', ['aberta', 'parcial'])
            ->count();

        $contasPagarVencidas = ContaPagar::where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '<', $hoje)
            ->whereIn('situacao', ['aberta', 'parcial'])
            ->count();

        $aReceber7Dias = (float) ContaReceber::where('empresa_id', $empresaId)
            ->whereBetween('data_vencimento', [$hoje, $em7Dias])
            ->whereIn('situacao', ['aberta', 'parcial'])
            ->sum('valor');

        $estoqueCritico = Produto::where('empresa_id', $empresaId)
            ->where('controla_estoque', true)
            ->where('ativo', true)
            ->whereColumn('estoque_atual', '<=', 'estoque_minimo')
            ->count();

        return view('welcome', [
            'vendasHoje'            => $vendasHoje,
            'faturamentoHoje'       => $faturamentoHoje,
            'contasReceberVencidas' => $contasReceberVencidas,
            'contasPagarVencidas'   => $contasPagarVencidas,
            'aReceber7Dias'         => $aReceber7Dias,
            'estoqueCritico'        => $estoqueCritico,
        ]);
    });

    Route::prefix('empresa')->middleware('perfil:admin')->group(function () {
        Route::get('/', [EmpresaController::class, 'index'])->name('empresa.index');
        Route::get('/novo', [EmpresaController::class, 'create'])->name('empresa.create');
        Route::post('/salvar', [EmpresaController::class, 'store'])->name('empresa.store');
        Route::get('{empresa}/editar', [EmpresaController::class, 'edit'])->name('empresa.edit');
        Route::put('{empresa}/editar/salvar', [EmpresaController::class, 'update'])->name('empresa.update');
        Route::delete('{empresa}/excluir', [EmpresaController::class, 'destroy'])->name('empresa.destroy');
    });

    Route::prefix('usuario')->middleware('perfil:admin')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('usuario.index');
        Route::get('/novo', [UsuarioController::class, 'create'])->name('usuario.create');
        Route::post('/salvar', [UsuarioController::class, 'store'])->name('usuario.store');
        Route::get('{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuario.edit');
        Route::put('{usuario}/editar/salvar', [UsuarioController::class, 'update'])->name('usuario.update');
        Route::delete('{usuario}/excluir', [UsuarioController::class, 'destroy'])->name('usuario.destroy');
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

    Route::prefix('funcionario')->middleware('perfil:admin,operador')->group(function () {
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

    Route::prefix('formaPagamento')->middleware('perfil:admin')->group(function () {
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

    Route::prefix('comissao')->middleware('perfil:admin,operador')->group(function () {
        Route::get('/', [ComissaoController::class, 'index'])->name('comissao.index');
        Route::post('{comissao}/pagar', [ComissaoController::class, 'pagar'])->name('comissao.pagar');
    });

    Route::prefix('venda')->group(function () {
        Route::get('/', [VendaController::class, 'index'])->name('venda.index');
        Route::get('/nova', [VendaController::class, 'create'])->name('venda.create');
        Route::post('/salvar', [VendaController::class, 'store'])->name('venda.store');
        Route::get('{venda}/pdf', [VendaController::class, 'pdf'])->name('venda.pdf');
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
        Route::get('{orcamento}/pdf', [OrcamentoController::class, 'pdf'])->name('orcamento.pdf');
        Route::get('{orcamento}/ver', [OrcamentoController::class, 'show'])->name('orcamento.show');
        Route::get('{orcamento}/editar', [OrcamentoController::class, 'edit'])->name('orcamento.edit');
        Route::put('{orcamento}/editar/salvar', [OrcamentoController::class, 'update'])->name('orcamento.update');
        Route::post('{orcamento}/aprovar', [OrcamentoController::class, 'aprovar'])->name('orcamento.aprovar');
        Route::post('{orcamento}/recusar', [OrcamentoController::class, 'recusar'])->name('orcamento.recusar');
        Route::post('{orcamento}/cancelar', [OrcamentoController::class, 'cancelar'])->name('orcamento.cancelar');
        Route::delete('{orcamento}/excluir', [OrcamentoController::class, 'destroy'])->name('orcamento.destroy');
    });

    Route::prefix('entrada-estoque')->group(function () {
        Route::get('/', [EntradaEstoqueController::class, 'index'])->name('entrada-estoque.index');
        Route::get('/nova', [EntradaEstoqueController::class, 'create'])->name('entrada-estoque.create');
        Route::post('/salvar', [EntradaEstoqueController::class, 'store'])->name('entrada-estoque.store');
        Route::get('{entradaEstoque}/ver', [EntradaEstoqueController::class, 'show'])->name('entrada-estoque.show');
        Route::get('{entradaEstoque}/editar', [EntradaEstoqueController::class, 'edit'])->name('entrada-estoque.edit');
        Route::put('{entradaEstoque}/editar/salvar', [EntradaEstoqueController::class, 'update'])->name('entrada-estoque.update');
        Route::post('{entradaEstoque}/confirmar', [EntradaEstoqueController::class, 'confirmar'])->name('entrada-estoque.confirmar');
        Route::post('{entradaEstoque}/cancelar', [EntradaEstoqueController::class, 'cancelar'])->name('entrada-estoque.cancelar');
        Route::delete('{entradaEstoque}/excluir', [EntradaEstoqueController::class, 'destroy'])->name('entrada-estoque.destroy');
    });

    Route::prefix('estoque')->group(function () {
        Route::get('/ajuste', [AjusteEstoqueController::class, 'create'])->name('estoque.ajuste.create');
        Route::post('/ajuste', [AjusteEstoqueController::class, 'store'])->name('estoque.ajuste.store');
        Route::get('/historico', [AjusteEstoqueController::class, 'historico'])->name('estoque.historico');
    });

    Route::prefix('categoria-financeira')->middleware('perfil:admin,financeiro')->group(function () {
        Route::get('/', [CategoriaFinanceiraController::class, 'index'])->name('categoria-financeira.index');
        Route::get('/nova', [CategoriaFinanceiraController::class, 'create'])->name('categoria-financeira.create');
        Route::post('/salvar', [CategoriaFinanceiraController::class, 'store'])->name('categoria-financeira.store');
        Route::get('{categoriaFinanceira}/editar', [CategoriaFinanceiraController::class, 'edit'])->name('categoria-financeira.edit');
        Route::put('{categoriaFinanceira}/editar/salvar', [CategoriaFinanceiraController::class, 'update'])->name('categoria-financeira.update');
        Route::delete('{categoriaFinanceira}/excluir', [CategoriaFinanceiraController::class, 'destroy'])->name('categoria-financeira.destroy');
    });

    Route::prefix('conta-receber')->middleware('perfil:admin,financeiro')->group(function () {
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

    Route::prefix('conta-pagar')->middleware('perfil:admin,financeiro')->group(function () {
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

    Route::get('/fluxo-de-caixa', [FluxoCaixaController::class, 'index'])
        ->middleware('perfil:admin,financeiro')
        ->name('fluxo-de-caixa.index');
    Route::get('/fluxo-de-caixa/csv', [FluxoCaixaController::class, 'csv'])
        ->middleware('perfil:admin,financeiro')
        ->name('fluxo-de-caixa.csv');

});
