<?php

use App\Http\Controllers\AuthController;
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

});