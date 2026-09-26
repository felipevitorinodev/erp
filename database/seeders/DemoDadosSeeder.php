<?php

namespace Database\Seeders;

use App\Models\CategoriaFinanceira;
use App\Models\Cliente;
use App\Models\Comissao;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\EntradaEstoque;
use App\Models\EntradaEstoqueItem;
use App\Models\FormaPagamento;
use App\Models\Fornecedor;
use App\Models\Funcionario;
use App\Models\Grupo;
use App\Models\MovimentacaoEstoque;
use App\Models\Orcamento;
use App\Models\OrcamentoItem;
use App\Models\Produto;
use App\Models\UnidadeMedida;
use App\Models\User;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDadosSeeder extends Seeder
{
    private int $empresaId;

    private \Faker\Generator $faker;

    public function run(): void
    {
        $empresa = Empresa::first();

        if (!$empresa) {
            $this->command?->error('Nenhuma empresa encontrada. Rode EmpresaSeeder antes.');
            return;
        }

        $this->empresaId = (int) $empresa->id;
        $this->faker = \Faker\Factory::create('pt_BR');

        $this->command?->info('Gerando massa de dados de teste...');

        DB::transaction(function () {
            $usuarios = $this->seedUsuarios();
            $grupos = $this->seedGrupos();
            $unidades = $this->seedUnidades();
            $formas = $this->seedFormasPagamento();
            $categoriasReceita = $this->seedCategorias('receita');
            $categoriasDespesa = $this->seedCategorias('despesa');
            $clientes = $this->seedClientes(150);
            $fornecedores = $this->seedFornecedores(80);
            $funcionarios = $this->seedFuncionarios(60);
            $produtos = $this->seedProdutos(120, $grupos, $unidades, $fornecedores);
            $this->seedVendas(100, $clientes, $produtos, $formas, $funcionarios, $usuarios, $categoriasReceita);
            $this->seedOrcamentos(80, $clientes, $produtos, $formas, $usuarios);
            $this->seedContasReceberAvulsas(60, $clientes, $formas, $categoriasReceita);
            $this->seedContasPagarAvulsas(60, $fornecedores, $formas, $categoriasDespesa);
            $this->seedEntradasEstoque(50, $fornecedores, $produtos, $categoriasDespesa);
        });

        $this->command?->info('Massa de dados gerada com sucesso.');
    }

    private function seedUsuarios(): array
    {
        // Mantém o login atual intacto (admin@dev.com / senha existente)
        $admin = User::where('email', 'admin@dev.com')->first();
        $ids = $admin ? [$admin->id] : User::where('empresa_id', $this->empresaId)->pluck('id')->all();

        $perfis = ['operador', 'operador', 'financeiro', 'operador', 'financeiro', 'operador', 'operador', 'financeiro'];

        foreach ($perfis as $i => $perfil) {
            $email = "usuario{$i}@demo.visys.local";
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'empresa_id' => $this->empresaId,
                    'name'       => $this->faker->name(),
                    'password'   => Hash::make('demo@123'),
                    'perfil'     => $perfil,
                    'ativo'      => true,
                ]
            );
            $ids[] = $user->id;
        }

        $ids = array_values(array_unique(array_filter($ids)));
        $this->command?->line('  Usuários: ' . count($ids) . ' (admin@dev.com preservado)');

        return $ids;
    }

    private function seedGrupos(): array
    {
        $pais = [
            'Eletrônicos', 'Informática', 'Escritório', 'Limpeza', 'Alimentos',
            'Bebidas', 'Ferragens', 'Embalagens', 'EPIs', 'Serviços',
            'Vestuário', 'Calçados', 'Móveis', 'Construção', 'Automotivo',
            'Pet Shop', 'Farmácia', 'Papelaria', 'Jardinagem', 'Utilidades',
        ];

        $ids = [];
        foreach ($pais as $nome) {
            $grupo = Grupo::firstOrCreate(
                ['empresa_id' => $this->empresaId, 'nome' => $nome, 'parent_id' => null],
                ['descricao' => "Grupo {$nome}", 'ativo' => true]
            );
            $ids[] = $grupo->id;

            for ($i = 1; $i <= 2; $i++) {
                $filho = Grupo::firstOrCreate(
                    [
                        'empresa_id' => $this->empresaId,
                        'nome'       => "{$nome} Sub {$i}",
                        'parent_id'  => $grupo->id,
                    ],
                    ['descricao' => "Subgrupo de {$nome}", 'ativo' => true]
                );
                $ids[] = $filho->id;
            }
        }

        $this->command?->line('  Grupos: ' . count($ids));

        return $ids;
    }

    private function seedUnidades(): array
    {
        $lista = [
            ['nome' => 'Unidade', 'sigla' => 'UN'],
            ['nome' => 'Caixa', 'sigla' => 'CX'],
            ['nome' => 'Quilograma', 'sigla' => 'KG'],
            ['nome' => 'Litro', 'sigla' => 'LT'],
            ['nome' => 'Metro', 'sigla' => 'MT'],
            ['nome' => 'Metro Quadrado', 'sigla' => 'M2'],
            ['nome' => 'Pacote', 'sigla' => 'PCT'],
            ['nome' => 'Par', 'sigla' => 'PAR'],
            ['nome' => 'Rolo', 'sigla' => 'RL'],
            ['nome' => 'Hora', 'sigla' => 'HR'],
        ];

        $ids = [];
        foreach ($lista as $item) {
            $u = UnidadeMedida::firstOrCreate(
                ['empresa_id' => $this->empresaId, 'sigla' => $item['sigla']],
                ['nome' => $item['nome'], 'ativo' => true]
            );
            $ids[] = $u->id;
        }

        $this->command?->line('  Unidades: ' . count($ids));

        return $ids;
    }

    private function seedFormasPagamento(): array
    {
        $formas = FormaPagamento::where('empresa_id', $this->empresaId)->pluck('nome')->all();

        $this->command?->line('  Formas de pagamento: ' . count($formas));

        return $formas;
    }

    private function seedCategorias(string $tipo): array
    {
        $nomes = $tipo === 'receita'
            ? [
                'Vendas', 'Serviços', 'Juros recebidos', 'Aluguéis', 'Outras receitas',
                'Comissões recebidas', 'Reembolso', 'Mensalidades', 'Consultoria', 'Assinaturas',
            ]
            : [
                'Fornecedores', 'Folha de pagamento', 'Impostos', 'Aluguel', 'Energia',
                'Marketing', 'Manutenção', 'Frete', 'Telefone/Internet', 'Combustível',
                'Material de escritório', 'Seguros',
            ];

        $ids = [];
        foreach ($nomes as $nome) {
            $cat = CategoriaFinanceira::firstOrCreate(
                ['empresa_id' => $this->empresaId, 'nome' => $nome, 'tipo' => $tipo],
                ['ativo' => true]
            );
            $ids[] = $cat->id;
        }

        $this->command?->line("  Categorias ({$tipo}): " . count($ids));

        return $ids;
    }

    private function seedClientes(int $qtd): array
    {
        $ids = Cliente::where('empresa_id', $this->empresaId)
            ->where('nome', '!=', 'Consumidor')
            ->pluck('id')
            ->all();

        $cidades = ['João Pessoa', 'Campina Grande', 'Recife', 'Natal', 'Fortaleza', 'Salvador'];
        $ufs = ['PB', 'PB', 'PE', 'RN', 'CE', 'BA'];

        for ($i = count($ids); $i < $qtd; $i++) {
            $pj = $i % 3 !== 0;
            $idx = $i % count($cidades);

            $cliente = Cliente::create([
                'empresa_id'          => $this->empresaId,
                'nome'                => $pj ? $this->faker->unique()->company() : $this->faker->unique()->name(),
                'nome_fantasia'       => $pj ? $this->faker->companySuffix() . ' Demo' : null,
                'cnpj'                => $pj ? $this->cnpjUnico() : null,
                'cpf'                 => $pj ? null : $this->cpfUnico(),
                'inscricao_estadual'  => $pj ? $this->faker->numerify('##########') : null,
                'inscricao_municipal' => $pj ? $this->faker->numerify('######') : null,
                'email'               => $this->faker->unique()->safeEmail(),
                'telefone'            => $this->faker->numerify('(83) ####-####'),
                'celular'             => $this->faker->numerify('(83) 9####-####'),
                'cep'                 => $this->faker->numerify('#####-###'),
                'logradouro'          => $this->faker->streetName(),
                'numero'              => (string) $this->faker->numberBetween(1, 9999),
                'complemento'         => $this->faker->optional()->secondaryAddress(),
                'bairro'              => $this->faker->citySuffix(),
                'cidade'              => $cidades[$idx],
                'estado'              => $ufs[$idx],
                'observacoes'         => 'Cliente gerado pelo DemoDadosSeeder.',
                'ativo'               => $i % 10 !== 0,
            ]);

            $ids[] = $cliente->id;
        }

        $this->command?->line('  Clientes: ' . count($ids));

        return $ids;
    }

    private function seedFornecedores(int $qtd): array
    {
        $ids = Fornecedor::where('empresa_id', $this->empresaId)->pluck('id')->all();

        for ($i = count($ids); $i < $qtd; $i++) {
            $pj = $i % 4 !== 0;

            $fornecedor = Fornecedor::create([
                'empresa_id'          => $this->empresaId,
                'nome'                => $pj ? $this->faker->unique()->company() : $this->faker->unique()->name(),
                'nome_fantasia'       => $pj ? 'Fornecedor Demo ' . ($i + 1) : null,
                'cnpj'                => $pj ? $this->cnpjUnico() : null,
                'cpf'                 => $pj ? null : $this->cpfUnico(),
                'inscricao_estadual'  => $pj ? $this->faker->numerify('##########') : null,
                'inscricao_municipal' => $pj ? $this->faker->numerify('######') : null,
                'email'               => $this->faker->unique()->companyEmail(),
                'telefone'            => $this->faker->numerify('(83) ####-####'),
                'celular'             => $this->faker->numerify('(83) 9####-####'),
                'cep'                 => $this->faker->numerify('#####-###'),
                'logradouro'          => $this->faker->streetName(),
                'numero'              => (string) $this->faker->numberBetween(1, 2000),
                'complemento'         => $this->faker->optional()->secondaryAddress(),
                'bairro'              => $this->faker->citySuffix(),
                'cidade'              => $this->faker->city(),
                'estado'              => $this->faker->stateAbbr(),
                'observacoes'         => 'Fornecedor gerado pelo DemoDadosSeeder.',
                'ativo'               => true,
            ]);

            $ids[] = $fornecedor->id;
        }

        $this->command?->line('  Fornecedores: ' . count($ids));

        return $ids;
    }

    private function seedFuncionarios(int $qtd): array
    {
        $ids = Funcionario::where('empresa_id', $this->empresaId)->pluck('id')->all();
        $cargos = ['Vendedor', 'Caixa', 'Estoquista', 'Gerente', 'Auxiliar', 'Comprador', 'Financeiro'];
        $depts = ['Comercial', 'Operações', 'Administrativo', 'Logística', 'Financeiro'];

        for ($i = count($ids); $i < $qtd; $i++) {
            $funcionario = Funcionario::create([
                'empresa_id'           => $this->empresaId,
                'usuario_id'           => null,
                'nome'                 => $this->faker->unique()->name(),
                'cpf'                  => $this->cpfUnico(),
                'rg'                   => $this->faker->numerify('#######'),
                'data_nascimento'      => $this->faker->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
                'email'                => $this->faker->unique()->safeEmail(),
                'telefone'             => $this->faker->numerify('(83) ####-####'),
                'celular'              => $this->faker->numerify('(83) 9####-####'),
                'cargo'                => $cargos[$i % count($cargos)],
                'departamento'         => $depts[$i % count($depts)],
                'salario'              => $this->faker->randomFloat(2, 1500, 8500),
                'percentual_comissao'  => in_array($cargos[$i % count($cargos)], ['Vendedor', 'Gerente'], true)
                    ? $this->faker->randomFloat(2, 1, 8)
                    : 0,
                'data_admissao'        => $this->faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
                'data_demissao'        => null,
                'tipo_contrato'        => $this->faker->randomElement(['CLT', 'PJ', 'Estágio']),
                'ativo'                => $i % 12 !== 0,
                'observacoes'          => 'Funcionário gerado pelo DemoDadosSeeder.',
            ]);

            $ids[] = $funcionario->id;
        }

        $this->command?->line('  Funcionários: ' . count($ids));

        return $ids;
    }

    private function seedProdutos(int $qtd, array $grupos, array $unidades, array $fornecedores): array
    {
        $ids = Produto::where('empresa_id', $this->empresaId)->pluck('id')->all();
        $ultimoCodigo = (int) Produto::where('empresa_id', $this->empresaId)->max('codigo');

        for ($i = count($ids); $i < $qtd; $i++) {
            $ultimoCodigo++;
            $servico = $i % 8 === 0;
            $custo = $this->faker->randomFloat(2, 5, 400);
            $venda = round($custo * $this->faker->randomFloat(2, 1.2, 2.5), 2);
            $estoque = $servico ? 0 : $this->faker->randomFloat(2, 0, 200);

            $produto = Produto::create([
                'empresa_id'         => $this->empresaId,
                'grupo_id'           => $this->faker->randomElement($grupos),
                'unidade_medida_id'  => $this->faker->randomElement($unidades),
                'fornecedor_id'      => $this->faker->randomElement($fornecedores),
                'codigo'             => str_pad((string) $ultimoCodigo, 5, '0', STR_PAD_LEFT),
                'codigo_barras'      => $this->faker->ean13(),
                'referencia'         => 'REF-' . strtoupper(Str::random(6)),
                'nome'               => ($servico ? 'Serviço ' : 'Produto ') . $this->faker->unique()->words(3, true),
                'descricao'          => $this->faker->sentence(),
                'tipo'               => $servico ? 2 : 1,
                'preco_custo'        => $custo,
                'preco_venda'        => $venda,
                'preco_minimo'       => round($venda * 0.9, 2),
                'margem_lucro'       => $custo > 0 ? round((($venda - $custo) / $custo) * 100, 2) : 0,
                'estoque_minimo'     => $servico ? 0 : $this->faker->randomFloat(2, 1, 20),
                'estoque_maximo'     => $servico ? 0 : $this->faker->randomFloat(2, 50, 500),
                'estoque_atual'      => $estoque,
                'controla_estoque'   => !$servico,
                'ativo'              => true,
                'ncm'                => $this->faker->numerify('########'),
            ]);

            if (!$servico && $estoque > 0) {
                MovimentacaoEstoque::create([
                    'empresa_id'     => $this->empresaId,
                    'produto_id'     => $produto->id,
                    'user_id'        => User::where('empresa_id', $this->empresaId)->value('id'),
                    'tipo'           => 'ajuste',
                    'quantidade'     => $estoque,
                    'estoque_antes'  => 0,
                    'estoque_depois' => $estoque,
                    'motivo'         => 'Estoque inicial (demo)',
                    'origem'         => 'ajuste_manual',
                    'origem_id'      => $produto->id,
                ]);
            }

            $ids[] = $produto->id;
        }

        $this->command?->line('  Produtos: ' . count($ids));

        return $ids;
    }

    private function seedVendas(
        int $qtd,
        array $clientes,
        array $produtos,
        array $formas,
        array $funcionarios,
        array $usuarios,
        array $categoriasReceita
    ): void {
        $existentes = Venda::where('empresa_id', $this->empresaId)->count();
        if ($existentes >= $qtd) {
            $this->command?->line("  Vendas: {$existentes} (já suficientes)");
            return;
        }

        $produtosModels = Produto::whereIn('id', $produtos)->get()->keyBy('id');
        $ultimoNumero = (int) Venda::where('empresa_id', $this->empresaId)->max('numero');
        $situacoes = ['em_andamento', 'confirmada', 'confirmada', 'confirmada', 'cancelada'];

        for ($i = $existentes; $i < $qtd; $i++) {
            $ultimoNumero++;
            $situacao = $situacoes[$i % count($situacoes)];
            $forma = $formas ? $formas[$i % count($formas)] : 'Dinheiro';
            $itensQtd = $this->faker->numberBetween(1, 4);
            $subtotal = 0;
            $itens = [];

            for ($j = 0; $j < $itensQtd; $j++) {
                $produtoId = $this->faker->randomElement($produtos);
                $produto = $produtosModels[$produtoId];
                $q = $this->faker->randomFloat(2, 1, 5);
                $preco = (float) $produto->preco_venda;
                $totalItem = round($q * $preco, 2);
                $subtotal += $totalItem;

                $itens[] = [
                    'produto_id'     => $produto->id,
                    'produto_nome'   => $produto->nome,
                    'produto_codigo' => $produto->codigo,
                    'quantidade'     => $q,
                    'preco_unitario' => $preco,
                    'desconto'       => 0,
                    'total'          => $totalItem,
                ];
            }

            $desconto = $this->faker->randomFloat(2, 0, min(20, $subtotal * 0.05));
            $total = round($subtotal - $desconto, 2);
            $funcionarioId = $this->faker->optional(0.7)->randomElement($funcionarios);
            $dataVenda = $this->faker->dateTimeBetween('-60 days', 'now')->format('Y-m-d');

            $venda = Venda::create([
                'empresa_id'      => $this->empresaId,
                'cliente_id'      => $this->faker->optional(0.85)->randomElement($clientes),
                'usuario_id'      => $this->faker->randomElement($usuarios),
                'funcionario_id'  => $funcionarioId,
                'numero'          => str_pad((string) $ultimoNumero, 6, '0', STR_PAD_LEFT),
                'data_venda'      => $dataVenda,
                'data_entrega'    => null,
                'subtotal'        => $subtotal,
                'desconto'        => $desconto,
                'acrescimo'       => 0,
                'total'           => $total,
                'forma_pagamento' => $forma,
                'observacoes'     => 'Venda demo.',
                'situacao'        => $situacao,
            ]);

            foreach ($itens as $item) {
                $item['venda_id'] = $venda->id;
                VendaItem::create($item);
            }

            if ($situacao === 'confirmada') {
                $formaModel = FormaPagamento::where('empresa_id', $this->empresaId)
                    ->where('nome', $forma)
                    ->first();

                if ($formaModel && $formaModel->gera_conta_receber) {
                    ContaReceber::create([
                        'empresa_id'      => $this->empresaId,
                        'cliente_id'      => $venda->cliente_id,
                        'venda_id'        => $venda->id,
                        'categoria_id'    => $this->faker->randomElement($categoriasReceita),
                        'descricao'       => 'Venda #' . $venda->numero,
                        'valor'           => $total,
                        'valor_pago'      => 0,
                        'data_vencimento' => now()->parse($dataVenda)->addDays((int) $formaModel->dias_vencimento)->toDateString(),
                        'forma_pagamento' => $forma,
                        'situacao'        => 'aberta',
                    ]);
                }

                if ($funcionarioId) {
                    $func = Funcionario::find($funcionarioId);
                    $perc = (float) ($func?->percentual_comissao ?? 0);
                    if ($perc > 0) {
                        Comissao::create([
                            'empresa_id'     => $this->empresaId,
                            'venda_id'       => $venda->id,
                            'funcionario_id' => $funcionarioId,
                            'percentual'     => $perc,
                            'valor'          => round($total * ($perc / 100), 2),
                            'situacao'       => $this->faker->randomElement(['pendente', 'pendente', 'paga']),
                            'data_pagamento' => null,
                        ]);
                    }
                }
            }
        }

        $this->command?->line('  Vendas: ' . Venda::where('empresa_id', $this->empresaId)->count());
    }

    private function seedOrcamentos(int $qtd, array $clientes, array $produtos, array $formas, array $usuarios): void
    {
        $existentes = Orcamento::where('empresa_id', $this->empresaId)->count();
        if ($existentes >= $qtd) {
            $this->command?->line("  Orçamentos: {$existentes} (já suficientes)");
            return;
        }

        $produtosModels = Produto::whereIn('id', $produtos)->get()->keyBy('id');
        $ultimoNumero = (int) Orcamento::where('empresa_id', $this->empresaId)->max('numero');
        $situacoes = ['pendente', 'pendente', 'aprovado', 'recusado', 'cancelado'];

        for ($i = $existentes; $i < $qtd; $i++) {
            $ultimoNumero++;
            $subtotal = 0;
            $itens = [];
            $n = $this->faker->numberBetween(1, 3);

            for ($j = 0; $j < $n; $j++) {
                $produto = $produtosModels[$this->faker->randomElement($produtos)];
                $q = $this->faker->randomFloat(2, 1, 4);
                $preco = (float) $produto->preco_venda;
                $totalItem = round($q * $preco, 2);
                $subtotal += $totalItem;
                $itens[] = compact('produto') + [
                    'quantidade' => $q,
                    'preco'      => $preco,
                    'total'      => $totalItem,
                ];
            }

            $orcamento = Orcamento::create([
                'empresa_id'      => $this->empresaId,
                'cliente_id'      => $this->faker->randomElement($clientes),
                'usuario_id'      => $this->faker->randomElement($usuarios),
                'numero'          => str_pad((string) $ultimoNumero, 6, '0', STR_PAD_LEFT),
                'data_orcamento'  => $this->faker->dateTimeBetween('-45 days', 'now')->format('Y-m-d'),
                'data_entrega'    => null,
                'subtotal'        => $subtotal,
                'desconto'        => 0,
                'acrescimo'       => 0,
                'total'           => $subtotal,
                'forma_pagamento' => $formas ? $formas[$i % count($formas)] : 'Pix',
                'observacoes'     => 'Orçamento demo.',
                'situacao'        => $situacoes[$i % count($situacoes)],
            ]);

            foreach ($itens as $item) {
                OrcamentoItem::create([
                    'orcamento_id'   => $orcamento->id,
                    'produto_id'     => $item['produto']->id,
                    'produto_nome'   => $item['produto']->nome,
                    'produto_codigo' => $item['produto']->codigo,
                    'quantidade'     => $item['quantidade'],
                    'preco_unitario' => $item['preco'],
                    'desconto'       => 0,
                    'total'          => $item['total'],
                ]);
            }
        }

        $this->command?->line('  Orçamentos: ' . Orcamento::where('empresa_id', $this->empresaId)->count());
    }

    private function seedContasReceberAvulsas(int $qtd, array $clientes, array $formas, array $categorias): void
    {
        $avulsas = ContaReceber::where('empresa_id', $this->empresaId)->whereNull('venda_id')->count();
        if ($avulsas >= $qtd) {
            $this->command?->line("  Contas a receber avulsas: {$avulsas}");
            return;
        }

        $situacoes = ['aberta', 'aberta', 'parcial', 'paga', 'cancelada'];

        for ($i = $avulsas; $i < $qtd; $i++) {
            $valor = $this->faker->randomFloat(2, 50, 5000);
            $situacao = $situacoes[$i % count($situacoes)];
            $pago = $situacao === 'paga' ? $valor : ($situacao === 'parcial' ? round($valor / 2, 2) : 0);

            ContaReceber::create([
                'empresa_id'      => $this->empresaId,
                'cliente_id'      => $this->faker->randomElement($clientes),
                'venda_id'        => null,
                'categoria_id'    => $this->faker->randomElement($categorias),
                'descricao'       => 'Recebimento avulso demo #' . ($i + 1),
                'valor'           => $valor,
                'valor_pago'      => $pago,
                'data_vencimento' => $this->faker->dateTimeBetween('-20 days', '+40 days')->format('Y-m-d'),
                'data_pagamento'  => $pago > 0 ? $this->faker->dateTimeBetween('-10 days', 'now')->format('Y-m-d') : null,
                'forma_pagamento' => $formas ? $formas[$i % count($formas)] : 'Pix',
                'situacao'        => $situacao,
                'observacoes'     => 'Conta gerada pelo DemoDadosSeeder.',
            ]);
        }

        $this->command?->line('  Contas a receber: ' . ContaReceber::where('empresa_id', $this->empresaId)->count());
    }

    private function seedContasPagarAvulsas(int $qtd, array $fornecedores, array $formas, array $categorias): void
    {
        $avulsas = ContaPagar::where('empresa_id', $this->empresaId)->whereNull('entrada_estoque_id')->count();
        if ($avulsas >= $qtd) {
            $this->command?->line("  Contas a pagar avulsas: {$avulsas}");
            return;
        }

        $situacoes = ['aberta', 'aberta', 'parcial', 'paga', 'cancelada'];

        for ($i = $avulsas; $i < $qtd; $i++) {
            $valor = $this->faker->randomFloat(2, 80, 8000);
            $situacao = $situacoes[$i % count($situacoes)];
            $pago = $situacao === 'paga' ? $valor : ($situacao === 'parcial' ? round($valor / 2, 2) : 0);

            ContaPagar::create([
                'empresa_id'      => $this->empresaId,
                'fornecedor_id'   => $this->faker->randomElement($fornecedores),
                'categoria_id'    => $this->faker->randomElement($categorias),
                'descricao'       => 'Despesa avulsa demo #' . ($i + 1),
                'valor'           => $valor,
                'valor_pago'      => $pago,
                'data_vencimento' => $this->faker->dateTimeBetween('-25 days', '+35 days')->format('Y-m-d'),
                'data_pagamento'  => $pago > 0 ? $this->faker->dateTimeBetween('-10 days', 'now')->format('Y-m-d') : null,
                'forma_pagamento' => $formas ? $formas[$i % count($formas)] : 'Boleto',
                'situacao'        => $situacao,
                'observacoes'     => 'Conta gerada pelo DemoDadosSeeder.',
            ]);
        }

        $this->command?->line('  Contas a pagar: ' . ContaPagar::where('empresa_id', $this->empresaId)->count());
    }

    private function seedEntradasEstoque(int $qtd, array $fornecedores, array $produtos, array $categorias): void
    {
        $existentes = EntradaEstoque::where('empresa_id', $this->empresaId)->count();
        if ($existentes >= $qtd) {
            $this->command?->line("  Entradas de estoque: {$existentes}");
            return;
        }

        $produtosModels = Produto::whereIn('id', $produtos)->where('controla_estoque', true)->get();
        if ($produtosModels->isEmpty()) {
            return;
        }

        $situacoes = ['rascunho', 'confirmada', 'confirmada', 'cancelada'];

        for ($i = $existentes; $i < $qtd; $i++) {
            $situacao = $situacoes[$i % count($situacoes)];
            $subtotal = 0;
            $itensData = [];
            $n = $this->faker->numberBetween(1, 3);

            for ($j = 0; $j < $n; $j++) {
                $produto = $produtosModels->random();
                $q = $this->faker->randomFloat(2, 1, 20);
                $custo = (float) $produto->preco_custo ?: $this->faker->randomFloat(2, 10, 100);
                $totalItem = round($q * $custo, 2);
                $subtotal += $totalItem;
                $itensData[] = [
                    'produto' => $produto,
                    'q'       => $q,
                    'custo'   => $custo,
                    'total'   => $totalItem,
                ];
            }

            $entrada = EntradaEstoque::create([
                'empresa_id'    => $this->empresaId,
                'fornecedor_id' => $this->faker->randomElement($fornecedores),
                'numero'        => 'NF-DEMO-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'data_entrada'  => $this->faker->dateTimeBetween('-40 days', 'now')->format('Y-m-d'),
                'subtotal'      => $subtotal,
                'desconto'      => 0,
                'total'         => $subtotal,
                'observacoes'   => 'Entrada demo.',
                'situacao'      => $situacao,
            ]);

            foreach ($itensData as $item) {
                EntradaEstoqueItem::create([
                    'entrada_estoque_id' => $entrada->id,
                    'produto_id'         => $item['produto']->id,
                    'produto_nome'       => $item['produto']->nome,
                    'produto_codigo'     => $item['produto']->codigo,
                    'quantidade'         => $item['q'],
                    'preco_unitario'     => $item['custo'],
                    'desconto'           => 0,
                    'total'              => $item['total'],
                ]);
            }

            if ($situacao === 'confirmada') {
                ContaPagar::create([
                    'empresa_id'         => $this->empresaId,
                    'fornecedor_id'      => $entrada->fornecedor_id,
                    'entrada_estoque_id' => $entrada->id,
                    'categoria_id'       => $this->faker->randomElement($categorias),
                    'descricao'          => 'Entrada NF ' . $entrada->numero,
                    'valor'              => $subtotal,
                    'valor_pago'         => 0,
                    'data_vencimento'    => now()->parse($entrada->data_entrada)->addDays(30)->toDateString(),
                    'forma_pagamento'    => 'Boleto',
                    'situacao'           => 'aberta',
                ]);
            }
        }

        $this->command?->line('  Entradas de estoque: ' . EntradaEstoque::where('empresa_id', $this->empresaId)->count());
    }

    private function cpfUnico(): string
    {
        do {
            $cpf = $this->faker->numerify('###.###.###-##');
        } while (
            Cliente::where('cpf', $cpf)->exists()
            || Fornecedor::where('cpf', $cpf)->exists()
            || Funcionario::where('cpf', $cpf)->exists()
        );

        return $cpf;
    }

    private function cnpjUnico(): string
    {
        do {
            $cnpj = $this->faker->numerify('##.###.###/####-##');
        } while (
            Cliente::where('cnpj', $cnpj)->exists()
            || Fornecedor::where('cnpj', $cnpj)->exists()
        );

        return $cnpj;
    }
}
