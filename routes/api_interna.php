<?php

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Funcionarios\Funcionario;
use App\Models\Grupos\Grupo;
use App\Models\Produtos\Produto;
use App\Models\UnidadesMedida\UnidadeMedida;
use App\Models\Vendas\Venda;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

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

    Route::get('/api/funcionarios/busca', function () {
        $q = trim((string) request('q', ''));
        $limit = $q === '' ? 5 : 10;

        $query = Funcionario::where('empresa_id', auth()->user()->empresa_id)
            ->where('ativo', true);

        if ($q !== '') {
            $query->where('nome', 'like', '%' . $q . '%');
        }

        return $query->orderBy('nome')
            ->select('id', 'nome', 'percentual_comissao')
            ->limit($limit)
            ->get();
    })->name('api.funcionarios.busca');

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

        if ((string) request('apenas_controlados') === '1') {
            $query->where('controla_estoque', true);
        }

        if ($q !== '') {
            $query->where(function ($builder) use ($q) {
                $builder->where('nome', 'like', '%' . $q . '%')
                    ->orWhere('codigo', 'like', '%' . $q . '%');
            });
        }

        return $query->orderBy('nome')
            ->with('unidadeMedida:id,sigla')
            ->select(
                'id',
                'nome',
                'codigo',
                'preco_venda',
                'preco_custo',
                'estoque_atual',
                'ncm',
                'cest',
                'cfop_padrao',
                'origem',
                'cst_icms',
                'cst_pis',
                'cst_cofins',
                'unidade_medida_id',
                'codigo_barras'
            )
            ->limit($limit)
            ->get()
            ->map(function (Produto $produto) {
                return [
                    'id' => $produto->id,
                    'nome' => $produto->nome,
                    'codigo' => $produto->codigo,
                    'preco_venda' => $produto->preco_venda,
                    'preco_custo' => $produto->preco_custo,
                    'estoque_atual' => $produto->estoque_atual,
                    'ncm' => $produto->ncm,
                    'cest' => $produto->cest,
                    'cfop_padrao' => $produto->cfop_padrao,
                    'origem' => $produto->origem,
                    'cst_icms' => $produto->cst_icms,
                    'cst_pis' => $produto->cst_pis,
                    'cst_cofins' => $produto->cst_cofins,
                    'unidade' => $produto->unidadeMedida?->sigla ?: 'UN',
                    'ean' => $produto->codigo_barras,
                ];
            });
    })->name('api.produtos.busca');

    Route::get('/api/produtos/{codigo}/busca-codigo', function (string $codigo) {
        $empresaId = auth()->user()->empresa_id;
        $codigo = trim($codigo);

        $produto = Produto::with('unidadeMedida')
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where(function ($q) use ($codigo) {
                $q->where('codigo', $codigo)->orWhere('codigo_barras', $codigo);
            })
            ->first();

        if (! $produto) {
            return response()->json(['encontrado' => false], 404);
        }

        return response()->json([
            'encontrado' => true,
            'produto' => [
                'id'               => $produto->id,
                'nome'             => $produto->nome,
                'codigo'           => $produto->codigo,
                'codigo_barras'    => $produto->codigo_barras,
                'preco_venda'      => (float) $produto->preco_venda,
                'estoque_atual'    => $produto->estoque_atual,
                'controla_estoque' => $produto->controla_estoque,
                'unidade'          => $produto->unidadeMedida?->sigla,
            ],
        ]);
    })->name('api.produtos.busca-codigo');

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
        if (!auth()->user()->pode('configuracoes')) {
            abort(403);
        }

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
    })->middleware('permissao:configuracoes')->name('api.empresas.busca');

    Route::get('/api/vendas/ultima', function () {
        $venda = Venda::where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id')
            ->first();

        if (! $venda) {
            return response()->json(['id' => null, 'numero' => null]);
        }

        return response()->json([
            'id'     => $venda->id,
            'numero' => $venda->numero,
        ]);
    })->name('api.vendas.ultima');

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

    // === CADASTROS RÁPIDOS (Modais) ===

    Route::post('/api/grupos/store', function () {
        $data = request()->validate([
            'nome' => 'required|string|max:100',
        ], [
            'nome.required' => 'O nome do grupo é obrigatório.',
        ]);

        $grupo = Grupo::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nome' => trim($data['nome']),
            'ativo' => true,
        ]);

        return response()->json([
            'success' => true,
            'id' => $grupo->id,
            'nome' => $grupo->nome,
            'message' => 'Grupo criado com sucesso!',
        ]);
    })->middleware('permissao:produtos')->name('api.grupos.store');

    Route::post('/api/unidades-medida/store', function () {
        $data = request()->validate([
            'nome' => 'required|string|max:50',
            'sigla' => 'required|string|max:10',
        ], [
            'nome.required' => 'O nome é obrigatório.',
            'sigla.required' => 'A sigla é obrigatória.',
        ]);

        $unidade = UnidadeMedida::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nome' => trim($data['nome']),
            'sigla' => strtoupper(trim($data['sigla'])),
            'ativo' => true,
        ]);

        return response()->json([
            'success' => true,
            'id' => $unidade->id,
            'nome' => $unidade->nome,
            'sigla' => $unidade->sigla,
            'message' => 'Unidade de medida criada com sucesso!',
        ]);
    })->middleware('permissao:produtos')->name('api.unidades-medida.store');

    Route::post('/api/fornecedores/store', function () {
        $data = request()->validate([
            'nome' => 'required|string|max:200',
            'cnpj' => 'nullable|string|max:18',
            'telefone' => 'nullable|string|max:20',
        ], [
            'nome.required' => 'O nome do fornecedor é obrigatório.',
        ]);

        $fornecedor = Fornecedor::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nome' => trim($data['nome']),
            'cnpj' => $data['cnpj'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'ativo' => true,
        ]);

        return response()->json([
            'success' => true,
            'id' => $fornecedor->id,
            'nome' => $fornecedor->nome,
            'message' => 'Fornecedor criado com sucesso!',
        ]);
    })->middleware('permissao:fornecedores')->name('api.fornecedores.store');

    Route::post('/api/clientes/store', function () {
        $data = request()->validate([
            'nome' => 'required|string|max:200',
            'cpf' => 'nullable|string|max:14',
            'cnpj' => 'nullable|string|max:18',
            'telefone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:100',
        ], [
            'nome.required' => 'O nome do cliente é obrigatório.',
        ]);

        $cliente = Cliente::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nome' => trim($data['nome']),
            'cpf' => $data['cpf'] ?? null,
            'cnpj' => $data['cnpj'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'email' => $data['email'] ?? null,
            'ativo' => true,
        ]);

        return response()->json([
            'success' => true,
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'message' => 'Cliente criado com sucesso!',
        ]);
    })->middleware('permissao:clientes,clientes.balcao')->name('api.clientes.store');

});
