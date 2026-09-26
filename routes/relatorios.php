<?php

use App\Models\CategoriaFinanceira;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Grupo;
use App\Models\Produto;
use App\Models\Venda;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    Route::get('/relatorios/vendas', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $query = Venda::with('cliente')
            ->where('empresa_id', $empresaId)
            ->whereDate('data_venda', '>=', $dataInicio)
            ->whereDate('data_venda', '<=', $dataFim);

        if (request()->filled('situacao')) {
            $query->where('situacao', request('situacao'));
        }

        $totalConfirmadas = (float) (clone $query)
            ->where('situacao', 'confirmada')
            ->sum('total');

        $vendas = $query->orderBy('data_venda')->orderBy('numero')->paginate(20)->withQueryString();

        return view('relatorios.vendas', [
            'vendas'            => $vendas,
            'total_confirmadas' => $totalConfirmadas,
            'filtros'           => [
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
                'situacao'    => request('situacao', ''),
            ],
        ]);
    })->name('relatorio.vendas');

    Route::get('/relatorios/produtos-mais-vendidos', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $produtos = DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.situacao', 'confirmada')
            ->whereNull('vendas.deleted_at')
            ->whereDate('vendas.data_venda', '>=', $dataInicio)
            ->whereDate('vendas.data_venda', '<=', $dataFim)
            ->groupBy('venda_itens.produto_nome', 'venda_itens.produto_codigo')
            ->select(
                'venda_itens.produto_nome',
                'venda_itens.produto_codigo',
                DB::raw('SUM(venda_itens.quantidade) as qtd_total'),
                DB::raw('SUM(venda_itens.total) as valor_total')
            )
            ->orderByDesc('qtd_total')
            ->paginate(20)
            ->withQueryString();

        return view('relatorios.produtos-mais-vendidos', [
            'produtos' => $produtos,
            'filtros'  => [
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
            ],
        ]);
    })->name('relatorio.produtos-mais-vendidos');

    Route::get('/relatorios/contas-receber', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $query = ContaReceber::with(['cliente', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        if (request()->filled('situacao')) {
            $query->where('situacao', request('situacao'));
        }

        if (request()->filled('categoria_id')) {
            $query->where('categoria_id', request('categoria_id'));
        }

        $todas = (clone $query)->with(['cliente', 'categoria'])->orderBy('data_vencimento')->get();

        $subtotaisCategoria = $todas
            ->groupBy(fn ($conta) => $conta->categoria?->nome ?? 'Sem categoria')
            ->map(fn ($grupo, $nome) => [
                'nome'       => $nome,
                'soma_valor' => (float) $grupo->sum('valor'),
                'soma_pago'  => (float) $grupo->sum('valor_pago'),
            ])
            ->sortKeys();

        $contas = $query->orderBy('data_vencimento')->paginate(20)->withQueryString();

        $categorias = CategoriaFinanceira::where('empresa_id', $empresaId)
            ->where('tipo', 'receita')
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.contas-receber', [
            'contas'               => $contas,
            'soma_valor'           => (float) $todas->sum('valor'),
            'soma_pago'            => (float) $todas->sum('valor_pago'),
            'subtotais_categoria'  => $subtotaisCategoria,
            'categorias'           => $categorias,
            'filtros'              => [
                'data_inicio'   => $dataInicio,
                'data_fim'      => $dataFim,
                'situacao'      => request('situacao', ''),
                'categoria_id'  => request('categoria_id', ''),
            ],
        ]);
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-receber');

    Route::get('/relatorios/contas-pagar', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $query = ContaPagar::with(['fornecedor', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        if (request()->filled('situacao')) {
            $query->where('situacao', request('situacao'));
        }

        if (request()->filled('categoria_id')) {
            $query->where('categoria_id', request('categoria_id'));
        }

        $todas = (clone $query)->with(['fornecedor', 'categoria'])->orderBy('data_vencimento')->get();

        $subtotaisCategoria = $todas
            ->groupBy(fn ($conta) => $conta->categoria?->nome ?? 'Sem categoria')
            ->map(fn ($grupo, $nome) => [
                'nome'       => $nome,
                'soma_valor' => (float) $grupo->sum('valor'),
                'soma_pago'  => (float) $grupo->sum('valor_pago'),
            ])
            ->sortKeys();

        $contas = $query->orderBy('data_vencimento')->paginate(20)->withQueryString();

        $categorias = CategoriaFinanceira::where('empresa_id', $empresaId)
            ->where('tipo', 'despesa')
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.contas-pagar', [
            'contas'              => $contas,
            'soma_valor'          => (float) $todas->sum('valor'),
            'soma_pago'           => (float) $todas->sum('valor_pago'),
            'subtotais_categoria' => $subtotaisCategoria,
            'categorias'          => $categorias,
            'filtros'             => [
                'data_inicio'  => $dataInicio,
                'data_fim'     => $dataFim,
                'situacao'     => request('situacao', ''),
                'categoria_id' => request('categoria_id', ''),
            ],
        ]);
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-pagar');

    Route::get('/relatorios/estoque', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = Produto::with(['grupos', 'unidadeMedida'])
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('controla_estoque', true);

        if (request()->filled('grupo_id')) {
            $query->where('grupo_id', request('grupo_id'));
        }

        if (request()->boolean('apenas_critico')) {
            $query->whereColumn('estoque_atual', '<=', 'estoque_minimo');
        }

        $produtos = $query->orderBy('nome')->paginate(20)->withQueryString();

        $grupos = Grupo::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.estoque', [
            'produtos' => $produtos,
            'grupos'   => $grupos,
            'filtros'  => [
                'grupo_id'       => request('grupo_id', ''),
                'apenas_critico' => request()->boolean('apenas_critico'),
            ],
        ]);
    })->name('relatorio.estoque');

    // ---- CSV exports ----

    Route::get('/relatorios/vendas/csv', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;
        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();
        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $query = Venda::with('cliente')
            ->where('empresa_id', $empresaId)
            ->whereDate('data_venda', '>=', $dataInicio)
            ->whereDate('data_venda', '<=', $dataFim);

        if (request()->filled('situacao')) {
            $query->where('situacao', request('situacao'));
        }

        $vendas = $query->orderBy('data_venda')->orderBy('numero')->get();
        $filename = 'relatorio-vendas-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($vendas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Nº', 'Data', 'Cliente', 'Total', 'Situação'], ';');
            foreach ($vendas as $v) {
                fputcsv($out, [
                    $v->numero,
                    $v->data_venda->format('d/m/Y'),
                    $v->cliente->nome ?? 'Consumidor Final',
                    number_format($v->total, 2, ',', '.'),
                    $v->situacao,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->name('relatorio.vendas.csv');

    Route::get('/relatorios/produtos-mais-vendidos/csv', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;
        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();
        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $produtos = DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.situacao', 'confirmada')
            ->whereNull('vendas.deleted_at')
            ->whereDate('vendas.data_venda', '>=', $dataInicio)
            ->whereDate('vendas.data_venda', '<=', $dataFim)
            ->groupBy('venda_itens.produto_nome', 'venda_itens.produto_codigo')
            ->select(
                'venda_itens.produto_nome',
                'venda_itens.produto_codigo',
                DB::raw('SUM(venda_itens.quantidade) as qtd_total'),
                DB::raw('SUM(venda_itens.total) as valor_total')
            )
            ->orderByDesc('qtd_total')
            ->get();

        $filename = 'relatorio-produtos-mais-vendidos-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($produtos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Código', 'Produto', 'Qtd', 'Valor Total'], ';');
            foreach ($produtos as $p) {
                fputcsv($out, [
                    $p->produto_codigo,
                    $p->produto_nome,
                    number_format($p->qtd_total, 2, ',', '.'),
                    number_format($p->valor_total, 2, ',', '.'),
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->name('relatorio.produtos-mais-vendidos.csv');

    Route::get('/relatorios/contas-receber/csv', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;
        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();
        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $query = ContaReceber::with(['cliente', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        if (request()->filled('situacao')) {
            $query->where('situacao', request('situacao'));
        }
        if (request()->filled('categoria_id')) {
            $query->where('categoria_id', request('categoria_id'));
        }

        $contas = $query->orderBy('data_vencimento')->get();
        $filename = 'relatorio-contas-receber-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($contas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Descrição', 'Cliente', 'Categoria', 'Vencimento', 'Valor', 'Pago', 'Situação'], ';');
            foreach ($contas as $c) {
                fputcsv($out, [
                    $c->descricao,
                    $c->cliente->nome ?? '—',
                    $c->categoria->nome ?? '—',
                    $c->data_vencimento->format('d/m/Y'),
                    number_format($c->valor, 2, ',', '.'),
                    number_format($c->valor_pago, 2, ',', '.'),
                    $c->situacao,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-receber.csv');

    Route::get('/relatorios/contas-pagar/csv', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;
        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();
        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        $query = ContaPagar::with(['fornecedor', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        if (request()->filled('situacao')) {
            $query->where('situacao', request('situacao'));
        }
        if (request()->filled('categoria_id')) {
            $query->where('categoria_id', request('categoria_id'));
        }

        $contas = $query->orderBy('data_vencimento')->get();
        $filename = 'relatorio-contas-pagar-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($contas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Descrição', 'Fornecedor', 'Categoria', 'Vencimento', 'Valor', 'Pago', 'Situação'], ';');
            foreach ($contas as $c) {
                fputcsv($out, [
                    $c->descricao,
                    $c->fornecedor->nome ?? '—',
                    $c->categoria->nome ?? '—',
                    $c->data_vencimento->format('d/m/Y'),
                    number_format($c->valor, 2, ',', '.'),
                    number_format($c->valor_pago, 2, ',', '.'),
                    $c->situacao,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-pagar.csv');

    Route::get('/relatorios/estoque/csv', function () {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = Produto::with(['grupos', 'unidadeMedida'])
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('controla_estoque', true);

        if (request()->filled('grupo_id')) {
            $query->where('grupo_id', request('grupo_id'));
        }
        if (request()->boolean('apenas_critico')) {
            $query->whereColumn('estoque_atual', '<=', 'estoque_minimo');
        }

        $produtos = $query->orderBy('nome')->get();
        $filename = 'relatorio-estoque-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($produtos) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Código', 'Produto', 'Grupo', 'Estoque', 'Mínimo', 'Unidade'], ';');
            foreach ($produtos as $p) {
                fputcsv($out, [
                    $p->codigo,
                    $p->nome,
                    $p->grupos->nome ?? '—',
                    number_format($p->estoque_atual, 2, ',', '.'),
                    number_format($p->estoque_minimo, 2, ',', '.'),
                    $p->unidadeMedida->sigla ?? '—',
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->name('relatorio.estoque.csv');

});
