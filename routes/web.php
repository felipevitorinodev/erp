<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\EmpresaController;
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

});