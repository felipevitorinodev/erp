<?php

use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Grupos\Grupo;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    $periodoPadrao = function (): array {
        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        return [$dataInicio, $dataFim];
    };

    $aplicarFiltrosVendas = function ($query) {
        if (request()->filled('situacao')) {
            $query->where('situacao', request('situacao'));
        }

        if (request()->filled('forma_pagamento')) {
            $query->where('forma_pagamento', 'like', '%' . request('forma_pagamento') . '%');
        }

        if (request()->filled('busca')) {
            $busca = request('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('numero', 'like', "%{$busca}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%"));
            });
        }

        return $query;
    };

    $aplicarFiltrosContas = function ($query, string $relacionamentoNome) {
        $situacao = request('situacao');
        if ($situacao === 'em_aberto' || $situacao === 'pendente') {
            $query->whereIn('situacao', ['aberta', 'parcial']);
        } elseif (request()->filled('situacao')) {
            $query->where('situacao', $situacao);
        }

        if (request()->filled('categoria_id')) {
            $query->where('categoria_id', request('categoria_id'));
        }

        $vencimento = request('vencimento');
        if ($vencimento === 'vencidos') {
            $query->whereDate('data_vencimento', '<', now()->toDateString())
                ->whereNotIn('situacao', ['paga', 'cancelada']);
        } elseif ($vencimento === 'a_vencer') {
            $query->whereDate('data_vencimento', '>=', now()->toDateString())
                ->whereNotIn('situacao', ['paga', 'cancelada']);
        }

        if (request()->filled('busca')) {
            $busca = request('busca');
            $query->where(function ($q) use ($busca, $relacionamentoNome) {
                $q->where('descricao', 'like', "%{$busca}%")
                    ->orWhereHas($relacionamentoNome, fn ($rel) => $rel->where('nome', 'like', "%{$busca}%"));
            });
        }

        return $query;
    };

    $queryProdutosMaisVendidos = function (int $empresaId, string $dataInicio, string $dataFim) {
        $query = DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->leftJoin('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.situacao', 'confirmada')
            ->whereNull('vendas.deleted_at')
            ->whereDate('vendas.data_venda', '>=', $dataInicio)
            ->whereDate('vendas.data_venda', '<=', $dataFim);

        if (request()->filled('grupo_id')) {
            $query->where('produtos.grupo_id', request('grupo_id'));
        }

        if (request()->filled('busca')) {
            $busca = request('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('venda_itens.produto_nome', 'like', "%{$busca}%")
                    ->orWhere('venda_itens.produto_codigo', 'like', "%{$busca}%");
            });
        }

        $ordenar = request('ordenar') === 'valor' ? 'valor_total' : 'qtd_total';

        return $query
            ->groupBy('venda_itens.produto_nome', 'venda_itens.produto_codigo')
            ->select(
                'venda_itens.produto_nome',
                'venda_itens.produto_codigo',
                DB::raw('SUM(venda_itens.quantidade) as qtd_total'),
                DB::raw('SUM(venda_itens.total) as valor_total')
            )
            ->orderByDesc($ordenar);
    };

    $aplicarFiltrosEstoque = function ($query) {
        if (request()->filled('grupo_id')) {
            $query->where('grupo_id', request('grupo_id'));
        }

        if (request()->boolean('apenas_critico')) {
            $query->whereColumn('estoque_atual', '<=', 'estoque_minimo');
        }

        $estoque = request('estoque');
        if ($estoque === 'zerado') {
            $query->where('estoque_atual', '<=', 0);
        } elseif ($estoque === 'com_estoque') {
            $query->where('estoque_atual', '>', 0);
        }

        if (request()->filled('busca')) {
            $busca = request('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                    ->orWhere('codigo', 'like', "%{$busca}%");
            });
        }

        return $query;
    };

    Route::get('/relatorios/vendas', function () use ($periodoPadrao, $aplicarFiltrosVendas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = Venda::with('cliente')
            ->where('empresa_id', $empresaId)
            ->whereDate('data_venda', '>=', $dataInicio)
            ->whereDate('data_venda', '<=', $dataFim);

        $aplicarFiltrosVendas($query);

        $totalConfirmadas = (float) (clone $query)
            ->where('situacao', 'confirmada')
            ->sum('total');

        $vendas = $query->orderBy('data_venda')->orderBy('numero')->paginate(20)->withQueryString();

        return view('relatorios.vendas', [
            'vendas'            => $vendas,
            'total_confirmadas' => $totalConfirmadas,
            'filtros'           => [
                'data_inicio'      => $dataInicio,
                'data_fim'         => $dataFim,
                'situacao'         => request('situacao', ''),
                'busca'            => request('busca', ''),
                'forma_pagamento'  => request('forma_pagamento', ''),
            ],
        ]);
    })->name('relatorio.vendas');

    Route::get('/relatorios/produtos-mais-vendidos', function () use ($periodoPadrao, $queryProdutosMaisVendidos) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $produtos = $queryProdutosMaisVendidos($empresaId, $dataInicio, $dataFim)
            ->paginate(20)
            ->withQueryString();

        $grupos = Grupo::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.produtos-mais-vendidos', [
            'produtos' => $produtos,
            'grupos'   => $grupos,
            'filtros'  => [
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
                'grupo_id'    => request('grupo_id', ''),
                'ordenar'     => request('ordenar', 'qtd'),
                'busca'       => request('busca', ''),
            ],
        ]);
    })->name('relatorio.produtos-mais-vendidos');

    Route::get('/relatorios/contas-receber', function () use ($periodoPadrao, $aplicarFiltrosContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = ContaReceber::with(['cliente', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        $aplicarFiltrosContas($query, 'cliente');

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
                'vencimento'    => request('vencimento', ''),
                'busca'         => request('busca', ''),
            ],
        ]);
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-receber');

    Route::get('/relatorios/contas-pagar', function () use ($periodoPadrao, $aplicarFiltrosContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = ContaPagar::with(['fornecedor', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        $aplicarFiltrosContas($query, 'fornecedor');

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
                'vencimento'   => request('vencimento', ''),
                'busca'        => request('busca', ''),
            ],
        ]);
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-pagar');

    Route::get('/relatorios/estoque', function () use ($aplicarFiltrosEstoque) {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = Produto::with(['grupos', 'unidadeMedida'])
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('controla_estoque', true);

        $aplicarFiltrosEstoque($query);

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
                'busca'          => request('busca', ''),
                'estoque'        => request('estoque', ''),
            ],
        ]);
    })->name('relatorio.estoque');

    // ---- CSV exports ----

    Route::get('/relatorios/vendas/csv', function () use ($periodoPadrao, $aplicarFiltrosVendas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = Venda::with('cliente')
            ->where('empresa_id', $empresaId)
            ->whereDate('data_venda', '>=', $dataInicio)
            ->whereDate('data_venda', '<=', $dataFim);

        $aplicarFiltrosVendas($query);

        $vendas = $query->orderBy('data_venda')->orderBy('numero')->get();
        $filename = 'relatorio-vendas-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($vendas) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            $situacoesVenda = [
                'em_andamento' => 'Em andamento',
                'confirmada' => 'Confirmada',
                'cancelada' => 'Cancelada',
            ];
            fputcsv($out, ['Nº', 'Data', 'Cliente', 'Forma de Pagamento', 'Total', 'Situação'], ';');
            foreach ($vendas as $v) {
                fputcsv($out, [
                    $v->numero,
                    $v->data_venda->format('d/m/Y'),
                    $v->cliente->nome ?? 'Consumidor Final',
                    $v->forma_pagamento ?: '—',
                    number_format($v->total, 2, ',', '.'),
                    $situacoesVenda[$v->situacao] ?? $v->situacao,
                ], ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->name('relatorio.vendas.csv');

    Route::get('/relatorios/produtos-mais-vendidos/csv', function () use ($periodoPadrao, $queryProdutosMaisVendidos) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $produtos = $queryProdutosMaisVendidos($empresaId, $dataInicio, $dataFim)->get();
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

    Route::get('/relatorios/contas-receber/csv', function () use ($periodoPadrao, $aplicarFiltrosContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = ContaReceber::with(['cliente', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        $aplicarFiltrosContas($query, 'cliente');

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

    Route::get('/relatorios/contas-pagar/csv', function () use ($periodoPadrao, $aplicarFiltrosContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = ContaPagar::with(['fornecedor', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        $aplicarFiltrosContas($query, 'fornecedor');

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

    Route::get('/relatorios/estoque/csv', function () use ($aplicarFiltrosEstoque) {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = Produto::with(['grupos', 'unidadeMedida'])
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('controla_estoque', true);

        $aplicarFiltrosEstoque($query);

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

    Route::get('/relatorios/vendas/pdf', function () use ($periodoPadrao, $aplicarFiltrosVendas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = Venda::with('cliente')
            ->where('empresa_id', $empresaId)
            ->whereDate('data_venda', '>=', $dataInicio)
            ->whereDate('data_venda', '<=', $dataFim);

        $aplicarFiltrosVendas($query);

        $situacoes = [
            'em_andamento' => 'Em andamento',
            'confirmada' => 'Confirmada',
            'cancelada' => 'Cancelada',
        ];

        $vendas = $query->orderBy('data_venda')->orderBy('numero')->get();
        $totalConfirmadas = (float) $vendas->where('situacao', 'confirmada')->sum('total');

        $linhas = $vendas->map(fn ($venda) => [
            '#' . $venda->numero,
            $venda->data_venda->format('d/m/Y'),
            $venda->cliente->nome ?? '— Consumidor Final —',
            $venda->forma_pagamento ?: '—',
            'R$ ' . number_format($venda->total, 2, ',', '.'),
            $situacoes[$venda->situacao] ?? $venda->situacao,
        ])->all();

        $resumoPartes = ['Situação: ' . ($situacoes[request('situacao')] ?? 'Todas')];
        if (request()->filled('busca')) {
            $resumoPartes[] = 'Busca: ' . request('busca');
        }
        if (request()->filled('forma_pagamento')) {
            $resumoPartes[] = 'Pagamento: ' . request('forma_pagamento');
        }

        $pdf = Pdf::loadView('pdf.relatorio', [
            'titulo' => 'Vendas por período',
            'periodo' => Carbon::parse($dataInicio)->format('d/m/Y') . ' a ' . Carbon::parse($dataFim)->format('d/m/Y'),
            'resumo' => implode(' · ', $resumoPartes),
            'secao' => 'Vendas',
            'colunas' => [
                ['label' => 'Número', 'width' => '12%'],
                ['label' => 'Data', 'width' => '12%'],
                ['label' => 'Cliente', 'width' => '28%'],
                ['label' => 'Pagamento', 'width' => '18%'],
                ['label' => 'Total', 'align' => 'right', 'width' => '15%'],
                ['label' => 'Situação', 'width' => '15%'],
            ],
            'linhas' => $linhas,
            'totais' => [
                ['label' => 'Total das confirmadas', 'valor' => 'R$ ' . number_format($totalConfirmadas, 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('relatorio-vendas-' . date('Y-m-d') . '.pdf');
    })->name('relatorio.vendas.pdf');

    Route::get('/relatorios/produtos-mais-vendidos/pdf', function () use ($periodoPadrao, $queryProdutosMaisVendidos) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $produtos = $queryProdutosMaisVendidos($empresaId, $dataInicio, $dataFim)->get();

        $linhas = $produtos->values()->map(fn ($produto, $indice) => [
            ($indice + 1) . 'º',
            $produto->produto_nome,
            $produto->produto_codigo ?: '—',
            number_format($produto->qtd_total, 2, ',', '.'),
            'R$ ' . number_format($produto->valor_total, 2, ',', '.'),
        ])->all();

        $resumoPartes = ['Somente vendas confirmadas'];
        if (request()->filled('grupo_id')) {
            $grupoNome = Grupo::where('empresa_id', $empresaId)->whereKey(request('grupo_id'))->value('nome') ?: '—';
            $resumoPartes[] = 'Grupo: ' . $grupoNome;
        }
        $resumoPartes[] = 'Ordenação: ' . (request('ordenar') === 'valor' ? 'Valor' : 'Quantidade');
        if (request()->filled('busca')) {
            $resumoPartes[] = 'Busca: ' . request('busca');
        }

        $pdf = Pdf::loadView('pdf.relatorio', [
            'titulo' => 'Produtos mais vendidos',
            'periodo' => Carbon::parse($dataInicio)->format('d/m/Y') . ' a ' . Carbon::parse($dataFim)->format('d/m/Y'),
            'resumo' => implode(' · ', $resumoPartes),
            'secao' => 'Produtos',
            'colunas' => [
                ['label' => 'Posição', 'width' => '10%'],
                ['label' => 'Produto', 'width' => '40%'],
                ['label' => 'Código', 'width' => '15%'],
                ['label' => 'Qtd. vendida', 'align' => 'right', 'width' => '15%'],
                ['label' => 'Valor total', 'align' => 'right', 'width' => '20%'],
            ],
            'linhas' => $linhas,
            'totais' => [
                ['label' => 'Valor total', 'valor' => 'R$ ' . number_format((float) $produtos->sum('valor_total'), 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('relatorio-produtos-mais-vendidos-' . date('Y-m-d') . '.pdf');
    })->name('relatorio.produtos-mais-vendidos.pdf');

    Route::get('/relatorios/contas-receber/pdf', function () use ($periodoPadrao, $aplicarFiltrosContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = ContaReceber::with(['cliente', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        $aplicarFiltrosContas($query, 'cliente');

        $situacoes = [
            'em_aberto' => 'Em aberto',
            'aberta' => 'Aberta',
            'parcial' => 'Parcial',
            'paga' => 'Paga',
            'cancelada' => 'Cancelada',
        ];

        $vencimentoLabels = [
            'vencidos' => 'Vencidos',
            'a_vencer' => 'A vencer',
        ];

        $contas = $query->orderBy('data_vencimento')->get();
        $linhas = $contas->map(fn ($conta) => [
            (string) $conta->id,
            $conta->descricao,
            $conta->cliente->nome ?? '—',
            $conta->categoria->nome ?? '—',
            $conta->data_vencimento->format('d/m/Y'),
            'R$ ' . number_format($conta->valor, 2, ',', '.'),
            'R$ ' . number_format($conta->valor_pago, 2, ',', '.'),
            $situacoes[$conta->situacao] ?? $conta->situacao,
        ])->all();

        $resumoPartes = [
            'Vencimento no período',
            'Situação: ' . ($situacoes[request('situacao')] ?? 'Todas'),
        ];
        if (request()->filled('vencimento')) {
            $resumoPartes[] = 'Filtro: ' . ($vencimentoLabels[request('vencimento')] ?? request('vencimento'));
        }
        if (request()->filled('busca')) {
            $resumoPartes[] = 'Busca: ' . request('busca');
        }

        $pdf = Pdf::loadView('pdf.relatorio', [
            'titulo' => 'Contas a receber',
            'periodo' => Carbon::parse($dataInicio)->format('d/m/Y') . ' a ' . Carbon::parse($dataFim)->format('d/m/Y'),
            'resumo' => implode(' · ', $resumoPartes),
            'secao' => 'Contas',
            'colunas' => [
                ['label' => 'Nº', 'width' => '8%'],
                ['label' => 'Descrição', 'width' => '22%'],
                ['label' => 'Cliente', 'width' => '18%'],
                ['label' => 'Categoria', 'width' => '14%'],
                ['label' => 'Vencimento', 'width' => '12%'],
                ['label' => 'Valor', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Pago', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Situação', 'width' => '6%'],
            ],
            'linhas' => $linhas,
            'totais' => [
                ['label' => 'Soma do valor', 'valor' => 'R$ ' . number_format((float) $contas->sum('valor'), 2, ',', '.')],
                ['label' => 'Soma do pago', 'valor' => 'R$ ' . number_format((float) $contas->sum('valor_pago'), 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('relatorio-contas-receber-' . date('Y-m-d') . '.pdf');
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-receber.pdf');

    Route::get('/relatorios/contas-pagar/pdf', function () use ($periodoPadrao, $aplicarFiltrosContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = ContaPagar::with(['fornecedor', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->whereDate('data_vencimento', '>=', $dataInicio)
            ->whereDate('data_vencimento', '<=', $dataFim);

        $aplicarFiltrosContas($query, 'fornecedor');

        $situacoes = [
            'em_aberto' => 'Em aberto',
            'aberta' => 'Aberta',
            'parcial' => 'Parcial',
            'paga' => 'Paga',
            'cancelada' => 'Cancelada',
        ];

        $vencimentoLabels = [
            'vencidos' => 'Vencidos',
            'a_vencer' => 'A vencer',
        ];

        $contas = $query->orderBy('data_vencimento')->get();
        $linhas = $contas->map(fn ($conta) => [
            (string) $conta->id,
            $conta->descricao,
            $conta->fornecedor->nome ?? '—',
            $conta->categoria->nome ?? '—',
            $conta->data_vencimento->format('d/m/Y'),
            'R$ ' . number_format($conta->valor, 2, ',', '.'),
            'R$ ' . number_format($conta->valor_pago, 2, ',', '.'),
            $situacoes[$conta->situacao] ?? $conta->situacao,
        ])->all();

        $resumoPartes = [
            'Vencimento no período',
            'Situação: ' . ($situacoes[request('situacao')] ?? 'Todas'),
        ];
        if (request()->filled('vencimento')) {
            $resumoPartes[] = 'Filtro: ' . ($vencimentoLabels[request('vencimento')] ?? request('vencimento'));
        }
        if (request()->filled('busca')) {
            $resumoPartes[] = 'Busca: ' . request('busca');
        }

        $pdf = Pdf::loadView('pdf.relatorio', [
            'titulo' => 'Contas a pagar',
            'periodo' => Carbon::parse($dataInicio)->format('d/m/Y') . ' a ' . Carbon::parse($dataFim)->format('d/m/Y'),
            'resumo' => implode(' · ', $resumoPartes),
            'secao' => 'Contas',
            'colunas' => [
                ['label' => 'Nº', 'width' => '8%'],
                ['label' => 'Descrição', 'width' => '22%'],
                ['label' => 'Fornecedor', 'width' => '18%'],
                ['label' => 'Categoria', 'width' => '14%'],
                ['label' => 'Vencimento', 'width' => '12%'],
                ['label' => 'Valor', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Pago', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Situação', 'width' => '6%'],
            ],
            'linhas' => $linhas,
            'totais' => [
                ['label' => 'Soma do valor', 'valor' => 'R$ ' . number_format((float) $contas->sum('valor'), 2, ',', '.')],
                ['label' => 'Soma do pago', 'valor' => 'R$ ' . number_format((float) $contas->sum('valor_pago'), 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('relatorio-contas-pagar-' . date('Y-m-d') . '.pdf');
    })->middleware('perfil:admin,financeiro')->name('relatorio.contas-pagar.pdf');

    Route::get('/relatorios/estoque/pdf', function () use ($aplicarFiltrosEstoque) {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = Produto::with(['grupos', 'unidadeMedida'])
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('controla_estoque', true);

        $aplicarFiltrosEstoque($query);

        $produtos = $query->orderBy('nome')->get();
        $linhas = $produtos->map(fn ($produto) => [
            $produto->codigo ?: '—',
            $produto->nome,
            $produto->grupos->nome ?? '—',
            $produto->unidadeMedida->sigla ?? ($produto->unidadeMedida->nome ?? '—'),
            number_format((float) $produto->estoque_minimo, 2, ',', '.'),
            number_format((float) $produto->estoque_atual, 2, ',', '.'),
        ])->all();

        $grupoNome = 'Todos';
        if (request()->filled('grupo_id')) {
            $grupoNome = Grupo::where('empresa_id', $empresaId)->whereKey(request('grupo_id'))->value('nome') ?: '—';
        }

        $estoqueLabels = [
            'zerado' => 'Zerado',
            'com_estoque' => 'Com estoque',
        ];

        $resumoPartes = ['Grupo: ' . $grupoNome];
        if (request()->boolean('apenas_critico')) {
            $resumoPartes[] = 'Somente estoque crítico';
        }
        if (request()->filled('estoque')) {
            $resumoPartes[] = 'Estoque: ' . ($estoqueLabels[request('estoque')] ?? request('estoque'));
        }
        if (request()->filled('busca')) {
            $resumoPartes[] = 'Busca: ' . request('busca');
        }

        $pdf = Pdf::loadView('pdf.relatorio', [
            'titulo' => 'Posição de estoque',
            'periodo' => now()->format('d/m/Y'),
            'resumo' => implode(' · ', $resumoPartes),
            'secao' => 'Produtos',
            'colunas' => [
                ['label' => 'Código', 'width' => '12%'],
                ['label' => 'Produto', 'width' => '34%'],
                ['label' => 'Grupo', 'width' => '18%'],
                ['label' => 'Unidade', 'width' => '12%'],
                ['label' => 'Mínimo', 'align' => 'right', 'width' => '12%'],
                ['label' => 'Atual', 'align' => 'right', 'width' => '12%'],
            ],
            'linhas' => $linhas,
            'totais' => [],
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('relatorio-estoque-' . date('Y-m-d') . '.pdf');
    })->name('relatorio.estoque.pdf');

});
