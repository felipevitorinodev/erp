<?php

use App\Http\Controllers\EmpresaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::prefix('empresa')->group(function() {
    Route::get('/', [EmpresaController::class, 'index']);
});