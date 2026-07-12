<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FornecedorController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\ProdutoController;
use App\Http\Controllers\UnidadeMedidaController;
use App\Http\Controllers\VendaController;
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

});