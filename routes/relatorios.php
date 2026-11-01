<?php

use App\Http\Controllers\Relatorios\HubController;
use App\Http\Controllers\Relatorios\OpcaoRelatorioController;
use App\Http\Controllers\Relatorios\RelatorioController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/relatorios', [HubController::class, 'index'])->name('relatorios.index');

    $registrar = function (string $uri, string $slug) {
        Route::get("/relatorios/{$uri}", function () use ($slug) {
            return app(RelatorioController::class)->pagina(request(), $slug);
        })->name("relatorio.{$slug}");

        Route::get("/relatorios/{$uri}/resumo", function () use ($slug) {
            return app(RelatorioController::class)->resumo(request(), $slug);
        })->name("relatorio.{$slug}.resumo");

        Route::get("/relatorios/{$uri}/dados", function () use ($slug) {
            return app(RelatorioController::class)->dados(request(), $slug);
        })->name("relatorio.{$slug}.dados");

        Route::get("/relatorios/{$uri}/csv", function () use ($slug) {
            return app(RelatorioController::class)->csv(request(), $slug);
        })->name("relatorio.{$slug}.csv");

        Route::get("/relatorios/{$uri}/pdf", function () use ($slug) {
            return app(RelatorioController::class)->pdf(request(), $slug);
        })->name("relatorio.{$slug}.pdf");
    };

    Route::middleware('permissao:relatorios')->group(function () use ($registrar) {
        Route::get('/relatorios/opcoes/clientes', [OpcaoRelatorioController::class, 'clientes'])->name('relatorio.opcoes.clientes');
        Route::get('/relatorios/opcoes/fornecedores', [OpcaoRelatorioController::class, 'fornecedores'])->name('relatorio.opcoes.fornecedores');
        Route::get('/relatorios/opcoes/funcionarios', [OpcaoRelatorioController::class, 'funcionarios'])->name('relatorio.opcoes.funcionarios');
        Route::get('/relatorios/opcoes/formas-pagamento', [OpcaoRelatorioController::class, 'formasPagamento'])->name('relatorio.opcoes.formas-pagamento');
        Route::get('/relatorios/opcoes/grupos', [OpcaoRelatorioController::class, 'grupos'])->name('relatorio.opcoes.grupos');
        Route::get('/relatorios/opcoes/produtos', [OpcaoRelatorioController::class, 'produtos'])->name('relatorio.opcoes.produtos');

        $registrar('vendas', 'vendas');
        $registrar('produtos-mais-vendidos', 'produtos-mais-vendidos');
        $registrar('vendas-por-vendedor', 'vendas-por-vendedor');
        $registrar('vendas-por-dia-semana', 'vendas-por-dia-semana');
        $registrar('ranking-clientes', 'ranking-clientes');
        $registrar('estoque', 'estoque');
    });

    Route::middleware(['permissao:relatorios', 'permissao:financeiro'])->group(function () use ($registrar) {
        Route::get('/relatorios/opcoes/categorias', [OpcaoRelatorioController::class, 'categorias'])->name('relatorio.opcoes.categorias');

        $registrar('contas-receber', 'contas-receber');
        $registrar('contas-pagar', 'contas-pagar');
        $registrar('inadimplencia-receber', 'inadimplencia-receber');
        $registrar('inadimplencia-pagar', 'inadimplencia-pagar');
        $registrar('clientes-com-debito', 'clientes-com-debito');
    });
});
