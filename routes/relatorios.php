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

    // O Dompdf monta o documento inteiro em memória; acima disso o CSV é o caminho.
    $limitePdf = 1000;

    $periodoPadrao = function (): array {
        request()->validate([
            'data_inicio' => 'nullable|date',
            'data_fim'    => 'nullable|date',
        ]);

        $dataInicio = request('data_inicio')
            ? Carbon::parse(request('data_inicio'))->toDateString()
            : now()->startOfMonth()->toDateString();

        $dataFim = request('data_fim')
            ? Carbon::parse(request('data_fim'))->toDateString()
            : now()->toDateString();

        return [$dataInicio, $dataFim];
    };

    // Evita que nomes/descrições iniciados por = + - @ sejam interpretados como fórmula no Excel
    $csvSeguro = fn (array $campos): array => array_map(
        fn ($valor) => is_string($valor) && preg_match('/^[=+\-@\t\r]/', $valor) && ! preg_match('/^-?[\d.,]+$/', $valor)
            ? "'" . $valor
            : $valor,
        $campos
    );

    $abrirCsv = function (array $cabecalho) {
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, $cabecalho, ';');

        return $out;
    };

    $registrosPdf = function ($query, ?int $total = null) use ($limitePdf): array {
        $total ??= $query->getCountForPagination();
        $registros = $query->limit($limitePdf)->get();
        $aviso = $total > $limitePdf
            ? "Exibindo {$limitePdf} de {$total} registros (exporte em CSV para a lista completa)"
            : null;

        return [$registros, $aviso];
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

    $queryVendas = function (int $empresaId, string $dataInicio, string $dataFim) use ($aplicarFiltrosVendas) {
        $query = Venda::with('cliente:id,nome')
            ->where('empresa_id', $empresaId)
            ->whereDataEntre('data_venda', $dataInicio, $dataFim);

        return $aplicarFiltrosVendas($query);
    };

    // Uma única passada no banco para todos os totalizadores; respeita os mesmos filtros da listagem.
    $totaisVendas = function ($query): array {
        $linha = (clone $query)->toBase()
            ->selectRaw('COUNT(*) as registros')
            ->selectRaw("SUM(CASE WHEN situacao = 'confirmada' THEN 1 ELSE 0 END) as qtd_confirmadas")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'confirmada' THEN total END), 0) as valor_confirmadas")
            ->selectRaw("SUM(CASE WHEN situacao = 'em_andamento' THEN 1 ELSE 0 END) as qtd_em_andamento")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'em_andamento' THEN total END), 0) as valor_em_andamento")
            ->selectRaw("SUM(CASE WHEN situacao = 'cancelada' THEN 1 ELSE 0 END) as qtd_canceladas")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'cancelada' THEN total END), 0) as valor_canceladas")
            ->first();

        $qtdConfirmadas = (int) ($linha->qtd_confirmadas ?? 0);
        $valorConfirmadas = round((float) ($linha->valor_confirmadas ?? 0), 2);

        return [
            'registros'          => (int) ($linha->registros ?? 0),
            'qtd_confirmadas'    => $qtdConfirmadas,
            'valor_confirmadas'  => $valorConfirmadas,
            'ticket_medio'       => $qtdConfirmadas > 0 ? round($valorConfirmadas / $qtdConfirmadas, 2) : 0.0,
            'qtd_em_andamento'   => (int) ($linha->qtd_em_andamento ?? 0),
            'valor_em_andamento' => round((float) ($linha->valor_em_andamento ?? 0), 2),
            'qtd_canceladas'     => (int) ($linha->qtd_canceladas ?? 0),
            'valor_canceladas'   => round((float) ($linha->valor_canceladas ?? 0), 2),
        ];
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
            $query->where('data_vencimento', '<', now()->toDateString())
                ->whereNotIn('situacao', ['paga', 'cancelada']);
        } elseif ($vencimento === 'a_vencer') {
            $query->where('data_vencimento', '>=', now()->toDateString())
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

    /** @param class-string<ContaReceber|ContaPagar> $modelo */
    $queryContas = function (string $modelo, string $relacionamentoNome, int $empresaId, string $dataInicio, string $dataFim) use ($aplicarFiltrosContas) {
        $query = $modelo::with([$relacionamentoNome . ':id,nome', 'categoria:id,nome'])
            ->where('empresa_id', $empresaId)
            ->whereDataEntre('data_vencimento', $dataInicio, $dataFim);

        return $aplicarFiltrosContas($query, $relacionamentoNome);
    };

    // Somas, saldos e subtotais numa única consulta agrupada. Canceladas continuam listadas,
    // mas só entram nas somas quando o filtro pede explicitamente canceladas.
    // Saldo em aberto/vencido considera apenas títulos abertos ou parciais (valor - valor_pago).
    $somasContas = function ($query): array {
        $hoje = now()->toDateString();
        $somavel = request('situacao') === 'cancelada' ? '1 = 1' : "situacao <> 'cancelada'";
        $emAberto = "situacao IN ('aberta', 'parcial')";

        $porCategoria = (clone $query)->toBase()
            ->select('categoria_id')
            ->selectRaw('COUNT(*) as registros')
            ->selectRaw("SUM(CASE WHEN {$somavel} THEN 1 ELSE 0 END) as qtd")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$somavel} THEN valor END), 0) as soma_valor")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$somavel} THEN valor_pago END), 0) as soma_pago")
            ->selectRaw("SUM(CASE WHEN {$emAberto} THEN 1 ELSE 0 END) as qtd_aberto")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$emAberto} THEN valor - valor_pago END), 0) as saldo_aberto")
            ->selectRaw("SUM(CASE WHEN {$emAberto} AND data_vencimento < ? THEN 1 ELSE 0 END) as qtd_vencido", [$hoje])
            ->selectRaw("COALESCE(SUM(CASE WHEN {$emAberto} AND data_vencimento < ? THEN valor - valor_pago END), 0) as saldo_vencido", [$hoje])
            ->groupBy('categoria_id')
            ->get();

        $somaveis = $porCategoria->filter(fn ($linha) => (int) $linha->qtd > 0);

        $nomes = CategoriaFinanceira::whereIn('id', $somaveis->pluck('categoria_id')->filter())
            ->pluck('nome', 'id');

        $subtotais = $somaveis
            ->groupBy(fn ($linha) => $linha->categoria_id ? ($nomes[$linha->categoria_id] ?? 'Sem categoria') : 'Sem categoria')
            ->map(fn ($grupo, $nome) => [
                'nome'       => $nome,
                'soma_valor' => round((float) $grupo->sum('soma_valor'), 2),
                'soma_pago'  => round((float) $grupo->sum('soma_pago'), 2),
            ])
            ->sortKeys();

        $somaValor = round((float) $porCategoria->sum('soma_valor'), 2);
        $somaPago = round((float) $porCategoria->sum('soma_pago'), 2);

        return [
            'registros'       => (int) $porCategoria->sum('registros'),
            'qtd'             => (int) $porCategoria->sum('qtd'),
            'soma_valor'      => $somaValor,
            'soma_pago'       => $somaPago,
            'percentual_pago' => $somaValor > 0 ? round($somaPago / $somaValor * 100, 1) : 0.0,
            'qtd_aberto'      => (int) $porCategoria->sum('qtd_aberto'),
            'saldo_aberto'    => round((float) $porCategoria->sum('saldo_aberto'), 2),
            'qtd_vencido'     => (int) $porCategoria->sum('qtd_vencido'),
            'saldo_vencido'   => round((float) $porCategoria->sum('saldo_vencido'), 2),
            'subtotais'       => $subtotais,
        ];
    };

    $queryProdutosMaisVendidos = function (int $empresaId, string $dataInicio, string $dataFim) {
        $query = DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.situacao', 'confirmada')
            ->whereNull('vendas.deleted_at')
            ->whereDataEntre('vendas.data_venda', $dataInicio, $dataFim);

        if (request()->filled('grupo_id')) {
            $query->join('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
                ->where('produtos.grupo_id', request('grupo_id'));
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
            ->orderByDesc($ordenar)
            ->orderBy('venda_itens.produto_nome')
            ->orderBy('venda_itens.produto_codigo');
    };

    $totaisProdutosMaisVendidos = function ($query): array {
        $linha = DB::query()
            ->fromSub((clone $query)->reorder(), 'mais_vendidos')
            ->selectRaw('COUNT(*) as produtos')
            ->selectRaw('COALESCE(SUM(qtd_total), 0) as qtd_total')
            ->selectRaw('COALESCE(SUM(valor_total), 0) as valor_total')
            ->first();

        return [
            'produtos'    => (int) ($linha->produtos ?? 0),
            'qtd_total'   => round((float) ($linha->qtd_total ?? 0), 3),
            'valor_total' => round((float) ($linha->valor_total ?? 0), 2),
        ];
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

    $queryEstoque = function (int $empresaId) use ($aplicarFiltrosEstoque) {
        $query = Produto::with(['grupos:id,nome', 'unidadeMedida:id,nome,sigla'])
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('controla_estoque', true);

        return $aplicarFiltrosEstoque($query)->orderBy('nome')->orderBy('id');
    };

    // "Crítico" usa a mesma regra do destaque da listagem; valor considera só saldo positivo a preço de custo.
    $totaisEstoque = function ($query): array {
        $linha = (clone $query)->toBase()->reorder()
            ->selectRaw('COUNT(*) as produtos')
            ->selectRaw('SUM(CASE WHEN estoque_atual <= estoque_minimo THEN 1 ELSE 0 END) as criticos')
            ->selectRaw('SUM(CASE WHEN estoque_atual <= 0 THEN 1 ELSE 0 END) as zerados')
            ->selectRaw('COALESCE(SUM(CASE WHEN estoque_atual > 0 THEN estoque_atual * COALESCE(preco_custo, 0) ELSE 0 END), 0) as valor_custo')
            ->first();

        return [
            'produtos'    => (int) ($linha->produtos ?? 0),
            'criticos'    => (int) ($linha->criticos ?? 0),
            'zerados'     => (int) ($linha->zerados ?? 0),
            'valor_custo' => round((float) ($linha->valor_custo ?? 0), 2),
        ];
    };

    Route::get('/relatorios/vendas', function () use ($periodoPadrao, $queryVendas, $totaisVendas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryVendas($empresaId, $dataInicio, $dataFim);
        $totais = $totaisVendas($query);

        $vendas = $query->orderBy('data_venda')->orderBy('numero')
            ->paginate(20, ['*'], 'page', null, $totais['registros'])
            ->withQueryString();

        return view('relatorios.vendas', [
            'vendas'            => $vendas,
            'totais'            => $totais,
            'filtros'           => [
                'data_inicio'      => $dataInicio,
                'data_fim'         => $dataFim,
                'situacao'         => request('situacao', ''),
                'busca'            => request('busca', ''),
                'forma_pagamento'  => request('forma_pagamento', ''),
            ],
        ]);
    })->middleware('permissao:relatorios')->name('relatorio.vendas');

    Route::get('/relatorios/produtos-mais-vendidos', function () use ($periodoPadrao, $queryProdutosMaisVendidos, $totaisProdutosMaisVendidos) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryProdutosMaisVendidos($empresaId, $dataInicio, $dataFim);
        $totais = $totaisProdutosMaisVendidos($query);

        $produtos = $query
            ->paginate(20, ['*'], 'page', null, $totais['produtos'])
            ->withQueryString();

        $grupos = Grupo::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.produtos-mais-vendidos', [
            'produtos' => $produtos,
            'totais'   => $totais,
            'grupos'   => $grupos,
            'filtros'  => [
                'data_inicio' => $dataInicio,
                'data_fim'    => $dataFim,
                'grupo_id'    => request('grupo_id', ''),
                'ordenar'     => request('ordenar', 'qtd'),
                'busca'       => request('busca', ''),
            ],
        ]);
    })->middleware('permissao:relatorios')->name('relatorio.produtos-mais-vendidos');

    Route::get('/relatorios/contas-receber', function () use ($periodoPadrao, $queryContas, $somasContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryContas(ContaReceber::class, 'cliente', $empresaId, $dataInicio, $dataFim);
        $somas = $somasContas($query);

        $contas = $query->orderBy('data_vencimento')->orderBy('id')
            ->paginate(20, ['*'], 'page', null, $somas['registros'])
            ->withQueryString();

        $categorias = CategoriaFinanceira::where('empresa_id', $empresaId)
            ->where('tipo', 'receita')
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.contas-receber', [
            'contas'               => $contas,
            'totais'               => $somas,
            'soma_valor'           => $somas['soma_valor'],
            'soma_pago'            => $somas['soma_pago'],
            'subtotais_categoria'  => $somas['subtotais'],
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
    })->middleware(['permissao:relatorios', 'permissao:financeiro'])->name('relatorio.contas-receber');

    Route::get('/relatorios/contas-pagar', function () use ($periodoPadrao, $queryContas, $somasContas) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryContas(ContaPagar::class, 'fornecedor', $empresaId, $dataInicio, $dataFim);
        $somas = $somasContas($query);

        $contas = $query->orderBy('data_vencimento')->orderBy('id')
            ->paginate(20, ['*'], 'page', null, $somas['registros'])
            ->withQueryString();

        $categorias = CategoriaFinanceira::where('empresa_id', $empresaId)
            ->where('tipo', 'despesa')
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.contas-pagar', [
            'contas'              => $contas,
            'totais'              => $somas,
            'soma_valor'          => $somas['soma_valor'],
            'soma_pago'           => $somas['soma_pago'],
            'subtotais_categoria' => $somas['subtotais'],
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
    })->middleware(['permissao:relatorios', 'permissao:financeiro'])->name('relatorio.contas-pagar');

    Route::get('/relatorios/estoque', function () use ($queryEstoque, $totaisEstoque) {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = $queryEstoque($empresaId);
        $totais = $totaisEstoque($query);

        $produtos = $query
            ->paginate(20, ['*'], 'page', null, $totais['produtos'])
            ->withQueryString();

        $grupos = Grupo::where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'nome']);

        return view('relatorios.estoque', [
            'produtos' => $produtos,
            'totais'   => $totais,
            'grupos'   => $grupos,
            'filtros'  => [
                'grupo_id'       => request('grupo_id', ''),
                'apenas_critico' => request()->boolean('apenas_critico'),
                'busca'          => request('busca', ''),
                'estoque'        => request('estoque', ''),
            ],
        ]);
    })->middleware('permissao:relatorios')->name('relatorio.estoque');

    // ---- CSV exports (lidos do banco em blocos, sem carregar tudo em memória) ----

    Route::get('/relatorios/vendas/csv', function () use ($periodoPadrao, $queryVendas, $csvSeguro, $abrirCsv) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryVendas($empresaId, $dataInicio, $dataFim)
            ->orderBy('data_venda')
            ->orderBy('numero')
            ->orderBy('id');
        $filename = 'relatorio-vendas-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query, $csvSeguro, $abrirCsv) {
            $out = $abrirCsv(['Nº', 'Data', 'Cliente', 'Forma de Pagamento', 'Total', 'Situação']);
            $situacoesVenda = [
                'em_andamento' => 'Em andamento',
                'confirmada' => 'Confirmada',
                'cancelada' => 'Cancelada',
            ];
            foreach ($query->lazy(500) as $v) {
                fputcsv($out, $csvSeguro([
                    $v->numero,
                    $v->data_venda->format('d/m/Y'),
                    $v->cliente->nome ?? 'Consumidor Final',
                    $v->forma_pagamento ?: '—',
                    number_format($v->total, 2, ',', '.'),
                    $situacoesVenda[$v->situacao] ?? $v->situacao,
                ]), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('permissao:relatorios')->name('relatorio.vendas.csv');

    Route::get('/relatorios/produtos-mais-vendidos/csv', function () use ($periodoPadrao, $queryProdutosMaisVendidos, $csvSeguro, $abrirCsv) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryProdutosMaisVendidos($empresaId, $dataInicio, $dataFim);
        $filename = 'relatorio-produtos-mais-vendidos-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query, $csvSeguro, $abrirCsv) {
            $out = $abrirCsv(['Código', 'Produto', 'Qtd', 'Valor Total']);
            foreach ($query->lazy(1000) as $p) {
                fputcsv($out, $csvSeguro([
                    $p->produto_codigo,
                    $p->produto_nome,
                    number_format($p->qtd_total, 2, ',', '.'),
                    number_format($p->valor_total, 2, ',', '.'),
                ]), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('permissao:relatorios')->name('relatorio.produtos-mais-vendidos.csv');

    Route::get('/relatorios/contas-receber/csv', function () use ($periodoPadrao, $queryContas, $csvSeguro, $abrirCsv) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryContas(ContaReceber::class, 'cliente', $empresaId, $dataInicio, $dataFim)
            ->orderBy('data_vencimento')
            ->orderBy('id');
        $filename = 'relatorio-contas-receber-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query, $csvSeguro, $abrirCsv) {
            $out = $abrirCsv(['Descrição', 'Cliente', 'Categoria', 'Vencimento', 'Valor', 'Pago', 'Situação']);
            foreach ($query->lazy(500) as $c) {
                fputcsv($out, $csvSeguro([
                    $c->descricao,
                    $c->cliente->nome ?? '—',
                    $c->categoria->nome ?? '—',
                    $c->data_vencimento->format('d/m/Y'),
                    number_format($c->valor, 2, ',', '.'),
                    number_format($c->valor_pago, 2, ',', '.'),
                    $c->situacao,
                ]), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware(['permissao:relatorios', 'permissao:financeiro'])->name('relatorio.contas-receber.csv');

    Route::get('/relatorios/contas-pagar/csv', function () use ($periodoPadrao, $queryContas, $csvSeguro, $abrirCsv) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryContas(ContaPagar::class, 'fornecedor', $empresaId, $dataInicio, $dataFim)
            ->orderBy('data_vencimento')
            ->orderBy('id');
        $filename = 'relatorio-contas-pagar-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query, $csvSeguro, $abrirCsv) {
            $out = $abrirCsv(['Descrição', 'Fornecedor', 'Categoria', 'Vencimento', 'Valor', 'Pago', 'Situação']);
            foreach ($query->lazy(500) as $c) {
                fputcsv($out, $csvSeguro([
                    $c->descricao,
                    $c->fornecedor->nome ?? '—',
                    $c->categoria->nome ?? '—',
                    $c->data_vencimento->format('d/m/Y'),
                    number_format($c->valor, 2, ',', '.'),
                    number_format($c->valor_pago, 2, ',', '.'),
                    $c->situacao,
                ]), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware(['permissao:relatorios', 'permissao:financeiro'])->name('relatorio.contas-pagar.csv');

    Route::get('/relatorios/estoque/csv', function () use ($queryEstoque, $csvSeguro, $abrirCsv) {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = $queryEstoque($empresaId);
        $filename = 'relatorio-estoque-' . date('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($query, $csvSeguro, $abrirCsv) {
            $out = $abrirCsv(['Código', 'Produto', 'Grupo', 'Estoque', 'Mínimo', 'Unidade']);
            foreach ($query->lazy(500) as $p) {
                fputcsv($out, $csvSeguro([
                    $p->codigo,
                    $p->nome,
                    $p->grupos->nome ?? '—',
                    number_format($p->estoque_atual, 2, ',', '.'),
                    number_format($p->estoque_minimo, 2, ',', '.'),
                    $p->unidadeMedida->sigla ?? '—',
                ]), ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    })->middleware('permissao:relatorios')->name('relatorio.estoque.csv');

    // ---- PDF exports ----

    Route::get('/relatorios/vendas/pdf', function () use ($periodoPadrao, $queryVendas, $totaisVendas, $registrosPdf) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryVendas($empresaId, $dataInicio, $dataFim);

        $situacoes = [
            'em_andamento' => 'Em andamento',
            'confirmada' => 'Confirmada',
            'cancelada' => 'Cancelada',
        ];

        $totais = $totaisVendas($query);
        [$vendas, $aviso] = $registrosPdf($query->orderBy('data_venda')->orderBy('numero'), $totais['registros']);

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
        if ($aviso) {
            $resumoPartes[] = $aviso;
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
                ['label' => 'Vendas confirmadas', 'valor' => (string) $totais['qtd_confirmadas']],
                ['label' => 'Ticket médio', 'valor' => 'R$ ' . number_format($totais['ticket_medio'], 2, ',', '.')],
                ['label' => 'Em andamento (' . $totais['qtd_em_andamento'] . ')', 'valor' => 'R$ ' . number_format($totais['valor_em_andamento'], 2, ',', '.')],
                ['label' => 'Canceladas (' . $totais['qtd_canceladas'] . ')', 'valor' => 'R$ ' . number_format($totais['valor_canceladas'], 2, ',', '.')],
                ['label' => 'Total das confirmadas', 'valor' => 'R$ ' . number_format($totais['valor_confirmadas'], 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('relatorio-vendas-' . date('Y-m-d') . '.pdf');
    })->middleware('permissao:relatorios')->name('relatorio.vendas.pdf');

    Route::get('/relatorios/produtos-mais-vendidos/pdf', function () use ($periodoPadrao, $queryProdutosMaisVendidos, $totaisProdutosMaisVendidos, $registrosPdf) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryProdutosMaisVendidos($empresaId, $dataInicio, $dataFim);
        $totais = $totaisProdutosMaisVendidos($query);
        [$produtos, $aviso] = $registrosPdf($query, $totais['produtos']);

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
        if ($aviso) {
            $resumoPartes[] = $aviso;
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
                ['label' => 'Produtos distintos', 'valor' => (string) $totais['produtos']],
                ['label' => 'Quantidade vendida', 'valor' => number_format($totais['qtd_total'], 2, ',', '.')],
                ['label' => 'Valor total', 'valor' => 'R$ ' . number_format($totais['valor_total'], 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('relatorio-produtos-mais-vendidos-' . date('Y-m-d') . '.pdf');
    })->middleware('permissao:relatorios')->name('relatorio.produtos-mais-vendidos.pdf');

    $pdfContas = function (string $modelo, string $relacionamentoNome, string $titulo, string $rotuloPessoa)
        use ($periodoPadrao, $queryContas, $somasContas, $registrosPdf) {
        $empresaId = auth()->user()->empresa_id ?? 0;
        [$dataInicio, $dataFim] = $periodoPadrao();

        $query = $queryContas($modelo, $relacionamentoNome, $empresaId, $dataInicio, $dataFim);

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

        $somas = $somasContas($query);
        [$contas, $aviso] = $registrosPdf($query->orderBy('data_vencimento')->orderBy('id'), $somas['registros']);

        $linhas = $contas->map(fn ($conta) => [
            (string) $conta->id,
            $conta->descricao,
            $conta->{$relacionamentoNome}->nome ?? '—',
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
        if ($aviso) {
            $resumoPartes[] = $aviso;
        }

        return Pdf::loadView('pdf.relatorio', [
            'titulo' => $titulo,
            'periodo' => Carbon::parse($dataInicio)->format('d/m/Y') . ' a ' . Carbon::parse($dataFim)->format('d/m/Y'),
            'resumo' => implode(' · ', $resumoPartes),
            'secao' => 'Contas',
            'colunas' => [
                ['label' => 'Nº', 'width' => '8%'],
                ['label' => 'Descrição', 'width' => '22%'],
                ['label' => $rotuloPessoa, 'width' => '18%'],
                ['label' => 'Categoria', 'width' => '14%'],
                ['label' => 'Vencimento', 'width' => '12%'],
                ['label' => 'Valor', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Pago', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Situação', 'width' => '6%'],
            ],
            'linhas' => $linhas,
            'totais' => [
                ['label' => 'Soma do valor', 'valor' => 'R$ ' . number_format($somas['soma_valor'], 2, ',', '.')],
                ['label' => 'Saldo em aberto (' . $somas['qtd_aberto'] . ')', 'valor' => 'R$ ' . number_format($somas['saldo_aberto'], 2, ',', '.')],
                ['label' => 'Vencido (' . $somas['qtd_vencido'] . ')', 'valor' => 'R$ ' . number_format($somas['saldo_vencido'], 2, ',', '.')],
                ['label' => 'Soma do pago', 'valor' => 'R$ ' . number_format($somas['soma_pago'], 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'landscape');
    };

    Route::get('/relatorios/contas-receber/pdf', function () use ($pdfContas) {
        return $pdfContas(ContaReceber::class, 'cliente', 'Contas a receber', 'Cliente')
            ->stream('relatorio-contas-receber-' . date('Y-m-d') . '.pdf');
    })->middleware(['permissao:relatorios', 'permissao:financeiro'])->name('relatorio.contas-receber.pdf');

    Route::get('/relatorios/contas-pagar/pdf', function () use ($pdfContas) {
        return $pdfContas(ContaPagar::class, 'fornecedor', 'Contas a pagar', 'Fornecedor')
            ->stream('relatorio-contas-pagar-' . date('Y-m-d') . '.pdf');
    })->middleware(['permissao:relatorios', 'permissao:financeiro'])->name('relatorio.contas-pagar.pdf');

    Route::get('/relatorios/estoque/pdf', function () use ($queryEstoque, $totaisEstoque, $registrosPdf) {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $query = $queryEstoque($empresaId);
        $totais = $totaisEstoque($query);
        [$produtos, $aviso] = $registrosPdf($query, $totais['produtos']);

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
        if ($aviso) {
            $resumoPartes[] = $aviso;
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
            'totais' => [
                ['label' => 'Produtos', 'valor' => (string) $totais['produtos']],
                ['label' => 'Estoque crítico', 'valor' => (string) $totais['criticos']],
                ['label' => 'Sem estoque', 'valor' => (string) $totais['zerados']],
                ['label' => 'Valor em estoque (custo)', 'valor' => 'R$ ' . number_format($totais['valor_custo'], 2, ',', '.'), 'destaque' => true],
            ],
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('relatorio-estoque-' . date('Y-m-d') . '.pdf');
    })->middleware('permissao:relatorios')->name('relatorio.estoque.pdf');

});
