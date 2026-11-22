<?php

namespace Database\Seeders\Desempenho;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Simula dois anos de operação de uma empresa, dia a dia, gravando o que as rotinas do sistema gravariam:
 * compras e entradas (estoque e conta a pagar), vendas (itens, baixa de estoque, conta a receber, comissão),
 * orçamentos aprovados que viram venda, OS faturadas, despesas fixas, recebimentos, cobranças e extratos OFX.
 *
 * O estoque é acompanhado em memória: nenhuma venda deixa saldo negativo e o estoque_atual final de cada
 * produto é o estoque_depois da última movimentação dele.
 */
final class Carga
{
    /** Margem sobre o custo por departamento: [mínima, máxima]. */
    private const MARGENS = [
        'Mercearia' => [0.20, 0.35], 'Bebidas' => [0.25, 0.40], 'Açougue e Frios' => [0.25, 0.35],
        'Higiene e Beleza' => [0.35, 0.55], 'Limpeza' => [0.30, 0.45], 'Celulares e Acessórios' => [0.18, 0.30],
        'Eletrodomésticos' => [0.20, 0.32], 'Utilidades Domésticas' => [0.40, 0.70], 'Ferragens e Construção' => [0.40, 0.80],
        'Vestuário e Calçados' => [0.70, 1.10], 'Assistência Técnica' => [0.60, 1.00],
    ];

    private const RAMO_FORNECEDOR = [
        'Mercearia' => 'de Alimentos', 'Bebidas' => 'de Bebidas', 'Açougue e Frios' => 'de Carnes e Frios',
        'Higiene e Beleza' => 'de Cosméticos', 'Limpeza' => 'de Produtos de Limpeza', 'Celulares e Acessórios' => 'Eletrônicos',
        'Eletrodomésticos' => 'Eletro', 'Utilidades Domésticas' => 'de Utilidades', 'Ferragens e Construção' => 'de Materiais de Construção',
        'Vestuário e Calçados' => 'de Confecções', 'Assistência Técnica' => 'de Peças e Componentes',
    ];

    private const FORMAS_BANCO = ['Pix', 'Boleto', 'Duplicata', 'Transferência'];

    private CarbonImmutable $inicio;

    private int $dias;

    private int $hojeDia;

    private int $inicioMesAtualDia;

    private int $inicioExtratoDia;

    private string $agora;

    /** @var array<int, string> */
    private array $datas = [];

    /** @var array<string, array{id: int, prazo: bool}> */
    private array $formas = [];

    private int $consumidorId;

    /** @var array<string, int> */
    private array $unidades = [];

    /** @var array<string, int> */
    private array $categorias = [];

    /** @var array{vendas: list<int>, atendimento: list<int>, financeiro: int, estoque: int} */
    private array $usuarios = ['vendas' => [], 'atendimento' => [], 'financeiro' => 0, 'estoque' => 0];

    /** @var list<array{id: int, usuario: int, percentual: float, nome: string}> */
    private array $vendedores = [];

    /** @var list<array{id: int, percentual: float, base: string, nome: string}> */
    private array $tecnicos = [];

    private float $folha = 0;

    /** @var array{id: list<int>, dia: list<int>, pj: list<bool>, nome: list<string>} */
    private array $clientes = ['id' => [], 'dia' => [], 'pj' => [], 'nome' => []];

    private int $liberados = 0;

    /** @var list<array{id: int, valor: float}> */
    private array $contratos = [];

    private ?int $inquilinoId = null;

    /** @var array<string, list<int>> */
    private array $fornecedoresPorDepto = [];

    /** @var array<int, array{nome: string, cnpj: string, uf: string}> */
    private array $fornecedores = [];

    /** @var array<string, int> */
    private array $fornecedoresDespesa = [];

    /** @var array<int, array<string, mixed>> */
    private array $produtos = [];

    /** @var array<int, float> */
    private array $estoque = [];

    /** @var array<int, true> */
    private array $faltando = [];

    /** @var array<int, true> */
    private array $emReposicao = [];

    /** @var list<float> */
    private array $acumulado = [];

    /** @var list<int> */
    private array $acumuladoIds = [];

    /** @var array<string, int> */
    private array $servicos = [];

    /** @var array<string, int> */
    private array $pecas = [];

    private int $numVenda = 0;

    private int $numOrcamento = 0;

    private int $numOs = 0;

    private int $numPedido = 0;

    private int $numNota = 10000;

    private int $proximoCodigo = 0;

    /** @var array<int, list<\Closure>> */
    private array $agenda = [];

    /** @var array<string, float> */
    private array $faturamentoMes = [];

    /** @var array<string, float> */
    private array $cartaoMes = [];

    /** @var array<int, float> */
    private array $cartaoDia = [];

    /** @var array<int, float> */
    private array $pixDia = [];

    /** @var array<string, list<array<string, mixed>>> */
    private array $transacoes = [];

    private int $fitid = 0;

    /**
     * @param  array<string, Lote>  $lotes
     */
    public function __construct(
        private readonly array $lotes,
        private readonly int $empresaId,
        private readonly float $escala,
        CarbonImmutable $hoje,
        private readonly bool $principal,
        private readonly string $senhaUsuarios,
        private readonly ?Command $console = null,
    ) {
        $this->inicio = $hoje->subMonthsNoOverflow(24)->addDay()->startOfDay();
        $this->dias = (int) $this->inicio->diffInDays($hoje->startOfDay()) + 1;
        $this->hojeDia = $this->dias - 1;
        $this->inicioMesAtualDia = (int) $this->inicio->diffInDays($hoje->startOfMonth());
        $this->inicioExtratoDia = (int) $this->inicio->diffInDays($hoje->startOfMonth()->subMonthsNoOverflow(3));
        $this->agora = $hoje->setTime(6, 0)->format('Y-m-d H:i:s');
    }

    public function executar(): void
    {
        $this->prepararBase();
        foreach ($this->lotes as $lote) {
            $lote->sincronizar();
        }
        $this->criarEquipe();
        $this->criarFornecedores();
        $this->criarProdutos();
        $this->criarClientes();
        $this->simular();
        $this->finalizar();
    }

    // ------------------------------------------------------------------ cadastros

    private function prepararBase(): void
    {
        $formasPadrao = [
            'Dinheiro' => ['avista', 0], 'Pix' => ['avista', 0], 'Cartão de Débito' => ['avista', 0], 'Cartão de Crédito' => ['avista', 0],
            'Transferência' => ['avista', 0], 'Duplicata' => ['prazo', 30], 'Boleto' => ['prazo', 30], 'Carnê' => ['prazo', 30],
            'Cheque' => ['prazo', 30], 'A Prazo' => ['prazo', 30], 'Crediário' => ['prazo', 30],
        ];
        $existentes = DB::table('formas_pagamento')->where('empresa_id', $this->empresaId)->whereNull('deleted_at')->get()->keyBy('nome');
        foreach ($formasPadrao as $nome => [$tipo, $dias]) {
            $forma = $existentes[$nome] ?? null;
            if ($forma === null) {
                $id = DB::table('formas_pagamento')->insertGetId([
                    'empresa_id' => $this->empresaId, 'nome' => $nome, 'tipo' => $tipo, 'gera_conta_receber' => $tipo === 'prazo',
                    'dias_vencimento' => $dias, 'ativo' => true, 'created_at' => $this->agora, 'updated_at' => $this->agora,
                ]);
                $this->formas[$nome] = ['id' => $id, 'prazo' => $tipo === 'prazo'];
            } else {
                $this->formas[$nome] = ['id' => (int) $forma->id, 'prazo' => $forma->tipo === 'prazo' || (bool) $forma->gera_conta_receber];
            }
        }

        $this->consumidorId = (int) (DB::table('clientes')->where('empresa_id', $this->empresaId)->where('nome', 'Consumidor')->value('id')
            ?: DB::table('clientes')->insertGetId([
                'empresa_id' => $this->empresaId, 'nome' => 'Consumidor', 'nome_fantasia' => 'Consumidor',
                'observacoes' => 'Cliente padrão para vendas sem identificação.', 'ativo' => true,
                'created_at' => $this->agora, 'updated_at' => $this->agora,
            ]));

        foreach (['UN' => 'Unidade', 'CX' => 'Caixa', 'KG' => 'Quilograma', 'PCT' => 'Pacote', 'SC' => 'Saco'] as $sigla => $nome) {
            $this->unidades[$sigla] = (int) (DB::table('unidades_medida')->where('empresa_id', $this->empresaId)->where('sigla', $sigla)->value('id')
                ?: DB::table('unidades_medida')->insertGetId([
                    'empresa_id' => $this->empresaId, 'nome' => $nome, 'sigla' => $sigla, 'ativo' => true,
                    'created_at' => $this->agora, 'updated_at' => $this->agora,
                ]));
        }

        foreach (['receita' => Catalogo::CATEGORIAS_RECEITA, 'despesa' => Catalogo::CATEGORIAS_DESPESA] as $tipo => $nomes) {
            foreach ($nomes as $nome) {
                $this->categorias[$nome] = (int) (DB::table('categorias_financeiras')
                    ->where('empresa_id', $this->empresaId)->where('nome', $nome)->where('tipo', $tipo)->value('id')
                    ?: DB::table('categorias_financeiras')->insertGetId([
                        'empresa_id' => $this->empresaId, 'nome' => $nome, 'tipo' => $tipo, 'ativo' => true,
                        'created_at' => $this->agora, 'updated_at' => $this->agora,
                    ]));
            }
        }
    }

    private function criarEquipe(): void
    {
        $emails = array_flip(DB::table('users')->pluck('email')->all());
        $quadro = $this->principal
            ? ['Vendedor' => 8, 'Operador de caixa' => 3, 'Técnico em eletrônica' => 4, 'Gerente' => 1, 'Assistente financeiro' => 1, 'Estoquista' => 2]
            : ['Vendedor' => 2, 'Operador de caixa' => 1, 'Técnico em eletrônica' => 1, 'Gerente' => 1];
        $salarios = ['Vendedor' => [1850, 2300], 'Operador de caixa' => [1650, 1800], 'Técnico em eletrônica' => [2400, 3200],
            'Gerente' => [4500, 5200], 'Assistente financeiro' => [2800, 3300], 'Estoquista' => [1700, 1900]];

        foreach ($quadro as $cargo => $quantidade) {
            for ($i = 0; $i < $quantidade; $i++) {
                $feminino = Sorteio::chance(0.5);
                $nome = Sorteio::item($feminino ? Catalogo::NOMES_FEMININOS : Catalogo::NOMES_MASCULINOS).' '
                    .Sorteio::item(Catalogo::SOBRENOMES).' '.Sorteio::item(Catalogo::SOBRENOMES);
                $usuarioId = null;
                $perfil = match ($cargo) {
                    'Gerente' => 'admin',
                    'Assistente financeiro' => 'financeiro',
                    'Vendedor', 'Operador de caixa' => 'vendedor',
                    default => null,
                };

                if ($perfil !== null) {
                    $partes = explode(' ', mb_strtolower(Sorteio::semAcento($nome)));
                    $email = $partes[0].'.'.end($partes).'@example.com';
                    for ($n = 2; isset($emails[$email]); $n++) {
                        $email = $partes[0].'.'.end($partes).$n.'@example.com';
                    }
                    $emails[$email] = true;
                    $usuarioId = $this->lotes['users']->reservarId();
                    $this->lotes['users']->add([
                        'id' => $usuarioId, 'empresa_id' => $this->empresaId, 'name' => $nome, 'email' => $email, 'perfil' => $perfil,
                        'ativo' => true, 'email_verified_at' => $this->agora, 'password' => $this->senhaUsuarios, 'remember_token' => null,
                        'created_at' => $this->momento(-Sorteio::int(30, 700)), 'updated_at' => $this->agora, 'deleted_at' => null,
                    ]);
                }

                $salario = round(Sorteio::real(...$salarios[$cargo]), -1);
                $this->folha += $salario;
                $id = $this->lotes['funcionarios']->reservarId();
                $percentual = $cargo === 'Vendedor' ? round(Sorteio::real(1, 3), 1) : 0.0;
                $percentualOs = $cargo === 'Técnico em eletrônica' ? (float) Sorteio::item([25, 30, 35, 40]) : 0.0;
                $base = Sorteio::chance(0.75) ? 'servicos' : 'total';
                $admissao = $this->inicio->subDays(Sorteio::int(60, 1500));

                $this->lotes['funcionarios']->add([
                    'id' => $id, 'empresa_id' => $this->empresaId, 'usuario_id' => $usuarioId, 'nome' => $nome, 'cpf' => Sorteio::cpf(),
                    'rg' => (string) Sorteio::int(1000000, 9999999), 'data_nascimento' => $admissao->subYears(Sorteio::int(19, 50))->subDays(Sorteio::int(0, 364))->toDateString(),
                    'email' => $usuarioId ? null : Sorteio::email($nome), 'telefone' => null, 'celular' => Sorteio::celular('83'),
                    'cargo' => $cargo, 'departamento' => match ($cargo) {
                        'Técnico em eletrônica' => 'Assistência técnica',
                        'Assistente financeiro' => 'Financeiro',
                        'Estoquista' => 'Estoque',
                        'Gerente' => 'Administração',
                        default => 'Vendas',
                    },
                    'salario' => $salario, 'percentual_comissao' => $percentual, 'comissao_os_percentual' => $percentualOs,
                    'comissao_os_base' => $base, 'data_admissao' => $admissao->toDateString(), 'data_demissao' => null, 'tipo_contrato' => 'CLT',
                    'observacoes' => null, 'ativo' => true, 'created_at' => $admissao->format('Y-m-d').' 09:00:00', 'updated_at' => $this->agora, 'deleted_at' => null,
                ]);

                if ($cargo === 'Vendedor') {
                    $this->vendedores[] = ['id' => $id, 'usuario' => (int) $usuarioId, 'percentual' => $percentual, 'nome' => $nome];
                    $this->usuarios['vendas'][] = (int) $usuarioId;
                    $this->usuarios['atendimento'][] = (int) $usuarioId;
                } elseif ($cargo === 'Operador de caixa') {
                    $this->usuarios['vendas'][] = (int) $usuarioId;
                    $this->usuarios['atendimento'][] = (int) $usuarioId;
                } elseif ($cargo === 'Técnico em eletrônica') {
                    $this->tecnicos[] = ['id' => $id, 'percentual' => $percentualOs, 'base' => $base, 'nome' => $nome];
                } elseif ($cargo === 'Gerente') {
                    $this->usuarios['atendimento'][] = (int) $usuarioId;
                    $this->usuarios['financeiro'] = $this->usuarios['financeiro'] ?: (int) $usuarioId;
                    $this->usuarios['estoque'] = (int) $usuarioId;
                } elseif ($cargo === 'Assistente financeiro') {
                    $this->usuarios['financeiro'] = (int) $usuarioId;
                }
            }
        }
    }

    private function criarFornecedores(): void
    {
        $nomes = [];
        $quantidades = ['Mercearia' => 14, 'Bebidas' => 8, 'Açougue e Frios' => 6, 'Higiene e Beleza' => 8, 'Limpeza' => 6,
            'Celulares e Acessórios' => 8, 'Eletrodomésticos' => 8, 'Utilidades Domésticas' => 5, 'Ferragens e Construção' => 8,
            'Vestuário e Calçados' => 6, 'Assistência Técnica' => 4];

        foreach ($quantidades as $depto => $quantidade) {
            $quantidade = max(2, (int) round($quantidade * ($this->principal ? 1 : 0.4)));
            for ($i = 0; $i < $quantidade; $i++) {
                do {
                    $fantasia = Sorteio::item(['Distribuidora', 'Comercial', 'Atacadão', 'Atacadista', 'Indústria'])
                        .' '.Sorteio::item(Catalogo::NOMES_FANTASIA);
                    $nome = $fantasia.' '.self::RAMO_FORNECEDOR[$depto].' '.Sorteio::item(Catalogo::SUFIXOS_PJ);
                } while (isset($nomes[$nome]));
                $nomes[$nome] = true;
                $longe = in_array($depto, ['Celulares e Acessórios', 'Eletrodomésticos', 'Vestuário e Calçados', 'Assistência Técnica'], true) && Sorteio::chance(0.5);
                $this->fornecedoresPorDepto[$depto][] = $this->fornecedor($nome, $fantasia, $longe);
            }
        }

        foreach (Catalogo::DESPESAS_FIXAS as [, , $nome]) {
            $this->fornecedoresDespesa[$nome] = $this->fornecedor($nome, preg_replace('/\s+(Ltda|S\/A|ME)$/u', '', $nome), false);
        }

        // Fornecedores eventuais (serviços, fretes, compras avulsas) que só aparecem no cadastro e em despesas variáveis.
        $diversos = (int) round(260 * $this->escala);
        $ramos = ['Transportes', 'Serviços Elétricos', 'Manutenção', 'Gráfica', 'Refrigeração', 'Embalagens', 'Informática', 'Segurança', 'Uniformes', 'Brindes'];
        for ($i = 0; $i < $diversos; $i++) {
            $fantasia = Sorteio::item(Catalogo::NOMES_FANTASIA).' '.Sorteio::item($ramos);
            $this->fornecedoresPorDepto['Diversos'][] = $this->fornecedor($fantasia.' '.Sorteio::item(Catalogo::SUFIXOS_PJ), $fantasia, false);
        }
    }

    private function fornecedor(string $nome, string $fantasia, bool $saoPaulo): int
    {
        $id = $this->lotes['fornecedores']->reservarId();
        $endereco = $saoPaulo
            ? ['cep' => sprintf('%05d-%03d', Sorteio::int(1000, 5999), Sorteio::int(0, 999)), 'logradouro' => Sorteio::item(['Rua Santa Ifigênia', 'Avenida Marquês de São Vicente', 'Rua Vinte e Cinco de Março', 'Avenida do Estado']),
                'numero' => (string) Sorteio::int(10, 2500), 'complemento' => null, 'bairro' => Sorteio::item(['Santa Ifigênia', 'Barra Funda', 'Brás', 'Centro']), 'cidade' => 'São Paulo', 'uf' => 'SP', 'ddd' => '11']
            : $this->endereco();
        $cnpj = Sorteio::cnpj();
        $this->fornecedores[$id] = ['nome' => $nome, 'cnpj' => $cnpj, 'uf' => $endereco['uf']];

        $this->lotes['fornecedores']->add([
            'id' => $id, 'empresa_id' => $this->empresaId, 'nome' => $nome, 'nome_fantasia' => $fantasia, 'cpf' => null, 'cnpj' => $cnpj,
            'inscricao_estadual' => (string) Sorteio::int(160000000, 169999999), 'inscricao_municipal' => null,
            'email' => 'comercial'.Sorteio::int(1, 999).'@example.com', 'telefone' => Sorteio::telefone($endereco['ddd']), 'celular' => Sorteio::celular($endereco['ddd']),
            'cep' => $endereco['cep'], 'logradouro' => $endereco['logradouro'], 'numero' => $endereco['numero'], 'complemento' => $endereco['complemento'],
            'bairro' => $endereco['bairro'], 'cidade' => $endereco['cidade'], 'estado' => $endereco['uf'], 'observacoes' => null, 'ativo' => true,
            'created_at' => $this->momento(-Sorteio::int(60, 1200)), 'updated_at' => $this->agora, 'deleted_at' => null,
        ]);

        return $id;
    }

    private function criarProdutos(): void
    {
        $this->proximoCodigo = (int) DB::table('produtos')->where('empresa_id', $this->empresaId)->max('codigo_seq');
        $fracao = $this->principal ? 1.0 : 0.3;

        foreach (Catalogo::DEPARTAMENTOS as $depto => $definicao) {
            $pai = $this->grupo($depto, null);
            $pesos = [];
            foreach ($definicao['itens'] as $item) {
                [$base, $marcas, $variantes, $ncm, $unidade] = $item;
                $extras = $item[5] ?? [];
                $cest = $item[6] ?? null;
                $grupo = $this->grupo($base, $pai);
                $combinacoes = [[]];
                foreach ($extras as $lista) {
                    $novas = [];
                    foreach ($combinacoes as $c) {
                        foreach ($lista as $valor) {
                            $novas[] = [...$c, $valor];
                        }
                    }
                    $combinacoes = $novas;
                }

                foreach ($marcas as $marca) {
                    $fatorMarca = 0.85 + (crc32($marca.$base) % 31) / 100;
                    foreach ($variantes as $variante => $precoBase) {
                        foreach ($combinacoes as $combinacao) {
                            if ($fracao < 1 && ! Sorteio::chance($fracao)) {
                                continue;
                            }
                            $nome = trim(preg_replace('/\s+/', ' ', implode(' ', [$base, $marca, $variante, ...$combinacao])));
                            $preco = self::arredondarPreco($precoBase * $fatorMarca * Sorteio::real(0.97, 1.03));
                            $margem = $depto === 'Celulares e Acessórios' && $preco < 300
                                ? Sorteio::real(0.8, 1.5)
                                : Sorteio::real(...self::MARGENS[$depto]);
                            [$minimo, $maximo] = $definicao['estoque'];
                            if ($preco > 1000) {
                                [$minimo, $maximo] = [Sorteio::int(1, 2), Sorteio::int(4, 10)];
                            }
                            $minimo = max(1, (int) round($minimo * Sorteio::real(0.6, 1.4)));
                            $maximo = max($minimo * 2, (int) round($maximo * Sorteio::real(0.6, 1.4)));
                            $importado = in_array($marca, ['Apple', 'Xiaomi', 'Baseus'], true) || str_starts_with($base, 'iPhone') || str_contains($base, 'Xiaomi');

                            $id = $this->produto([
                                'depto' => $depto, 'grupo' => $grupo, 'nome' => $nome, 'tipo' => 1, 'preco' => $preco, 'margem' => $margem,
                                'ncm' => $ncm, 'cest' => $cest, 'unidade' => $unidade, 'icms' => $cest ? '500' : $definicao['icms'],
                                'origem' => $importado ? 2 : 0, 'kg' => ! empty($definicao['kg']), 'qtd' => $definicao['qtd'],
                                'minimo' => $minimo, 'maximo' => $maximo, 'entrega' => ! empty($definicao['entrega']), 'controla' => true,
                                'fornecedor' => Sorteio::item($this->fornecedoresPorDepto[$depto]),
                            ]);

                            if ($this->produtos[$id]['linha']['ativo']) {
                                $pesos[$id] = Sorteio::real(0.15, 2.5) * ($preco < 20 ? 1.6 : 1.0);
                            }
                        }
                    }
                }
            }

            // O giro do departamento é dividido entre os produtos dele.
            $soma = array_sum($pesos) ?: 1;
            foreach ($pesos as $id => $peso) {
                $total = ($this->acumulado === [] ? 0 : end($this->acumulado)) + $definicao['giro'] * $peso / $soma;
                $this->acumulado[] = $total;
                $this->acumuladoIds[] = $id;
            }
        }

        $assistencia = $this->grupo('Assistência Técnica', null);
        $grupoPecas = $this->grupo('Peças e componentes', $assistencia);
        foreach (Catalogo::PECAS as [$peca, $modelos, $precoBase, $ncm]) {
            foreach ($modelos as $modelo) {
                $preco = self::arredondarPreco($precoBase * (str_contains($modelo, 'iPhone') ? 1.8 : Sorteio::real(0.9, 1.3)));
                $nome = $peca.' '.$modelo;
                $this->pecas[$nome] = $this->produto([
                    'depto' => 'Assistência Técnica', 'grupo' => $grupoPecas, 'nome' => $nome, 'tipo' => 1, 'preco' => $preco,
                    'margem' => Sorteio::real(...self::MARGENS['Assistência Técnica']), 'ncm' => $ncm, 'cest' => null, 'unidade' => 'UN',
                    'icms' => '102', 'origem' => 2, 'kg' => false, 'qtd' => [1, 1], 'minimo' => 1, 'maximo' => Sorteio::int(3, 8),
                    'entrega' => false, 'controla' => true, 'fornecedor' => Sorteio::item($this->fornecedoresPorDepto['Assistência Técnica']),
                ]);
            }
        }

        $grupoServicos = $this->grupo('Serviços', $assistencia);
        foreach (Catalogo::SERVICOS as $nome => $preco) {
            $this->servicos[$nome] = $this->produto([
                'depto' => 'Serviços', 'grupo' => $grupoServicos, 'nome' => $nome, 'tipo' => 2, 'preco' => $preco, 'margem' => 0.0,
                'ncm' => null, 'cest' => null, 'unidade' => 'UN', 'icms' => null, 'origem' => 0, 'kg' => false, 'qtd' => [1, 1],
                'minimo' => 0, 'maximo' => 0, 'entrega' => false, 'controla' => false, 'fornecedor' => null,
            ]);
        }

        // Inventário inicial: a mesma movimentação que o cadastro de produto grava ("Estoque inicial").
        foreach ($this->produtos as $id => $produto) {
            if (! $produto['controla']) {
                continue;
            }
            $quantidade = $produto['kg']
                ? round(Sorteio::real($produto['minimo'], $produto['maximo']), 1)
                : (float) Sorteio::int($produto['minimo'], $produto['maximo']);
            $this->movimentar($id, 'ajuste', $quantidade, 'Estoque inicial', 'ajuste_manual', $id, $this->momento(-1, 7, 7), $this->usuarios['estoque']);
        }
    }

    private function grupo(string $nome, ?int $pai): int
    {
        $id = $this->lotes['grupos']->reservarId();
        $this->lotes['grupos']->add([
            'id' => $id, 'empresa_id' => $this->empresaId, 'parent_id' => $pai, 'nome' => $nome, 'descricao' => null, 'ativo' => true,
            'created_at' => $this->momento(-900), 'updated_at' => $this->agora, 'deleted_at' => null,
        ]);

        return $id;
    }

    /** @param  array<string, mixed>  $p */
    private function produto(array $p): int
    {
        $id = $this->lotes['produtos']->reservarId();
        $custo = $p['tipo'] === 2 ? 0.0 : round($p['preco'] / (1 + $p['margem']), 2);
        $codigo = str_pad((string) ++$this->proximoCodigo, 6, '0', STR_PAD_LEFT);
        $ean = $p['tipo'] === 1 && ! $p['kg'] ? Sorteio::ean13() : null;

        $this->produtos[$id] = $p + [
            'codigo' => $codigo, 'custo' => $custo, 'ean' => $ean,
            'linha' => [
                'id' => $id, 'empresa_id' => $this->empresaId, 'grupo_id' => $p['grupo'], 'unidade_medida_id' => $this->unidades[$p['unidade']],
                'fornecedor_id' => $p['fornecedor'], 'perfil_fiscal_id' => null, 'codigo' => $codigo, 'codigo_barras' => $ean,
                'referencia' => $p['tipo'] === 1 && Sorteio::chance(0.25) ? 'REF-'.Sorteio::int(1000, 99999) : null,
                'nome' => $p['nome'], 'descricao' => null, 'tipo' => $p['tipo'], 'preco_custo' => $custo, 'preco_venda' => $p['preco'],
                'preco_minimo' => $p['preco'] >= 500 ? round($p['preco'] * 0.92, 2) : 0.0,
                'margem_lucro' => $custo > 0 ? round(($p['preco'] / $custo - 1) * 100, 2) : null,
                'estoque_minimo' => $p['minimo'], 'estoque_maximo' => $p['maximo'], 'estoque_atual' => 0,
                'peso' => null, 'altura' => null, 'largura' => null, 'profundidade' => null, 'foto_url' => null,
                'ncm' => $p['ncm'], 'cest' => $p['cest'], 'cfop_padrao' => $p['tipo'] === 1 ? '5102' : null, 'origem' => $p['origem'],
                'cst_icms' => $p['icms'], 'cst_pis' => $p['tipo'] === 1 ? '07' : null, 'cst_cofins' => $p['tipo'] === 1 ? '07' : null,
                'aliquota_icms' => null, 'aliquota_pis' => null, 'aliquota_cofins' => null, 'controla_estoque' => $p['controla'],
                'ativo' => $p['tipo'] === 2 || Sorteio::chance(0.97), 'created_at' => $this->momento(-Sorteio::int(30, 1000)),
                'updated_at' => $this->agora, 'deleted_at' => null,
            ],
        ];
        $this->estoque[$id] = 0.0;

        return $id;
    }

    private function criarClientes(): void
    {
        $total = (int) round(40000 * $this->escala);
        $dias = [];
        for ($i = 0; $i < $total; $i++) {
            $dias[] = Sorteio::chance(0.7) ? -Sorteio::int(1, 1100) : Sorteio::int(0, $this->hojeDia);
        }
        sort($dias);

        foreach ($dias as $dia) {
            $pj = Sorteio::chance(0.08);
            $endereco = $this->endereco();
            if ($pj) {
                $fantasia = Sorteio::item(Catalogo::RAMOS_CLIENTE_PJ).' '.Sorteio::item(Catalogo::NOMES_FANTASIA);
                $nome = $fantasia.' '.Sorteio::item(Catalogo::SUFIXOS_PJ);
            } else {
                $nome = Sorteio::item(Sorteio::chance(0.52) ? Catalogo::NOMES_FEMININOS : Catalogo::NOMES_MASCULINOS).' '
                    .Sorteio::item(Catalogo::SOBRENOMES).' '.Sorteio::item(Catalogo::SOBRENOMES);
                $fantasia = null;
            }
            $temEndereco = $pj || Sorteio::chance(0.72);
            $id = $this->lotes['clientes']->reservarId();
            $this->lotes['clientes']->add([
                'id' => $id, 'empresa_id' => $this->empresaId, 'nome' => $nome, 'nome_fantasia' => $fantasia,
                'cpf' => ! $pj && Sorteio::chance(0.82) ? Sorteio::cpf() : null, 'cnpj' => $pj && Sorteio::chance(0.95) ? Sorteio::cnpj() : null,
                'inscricao_estadual' => $pj && Sorteio::chance(0.6) ? (string) Sorteio::int(160000000, 169999999) : null, 'inscricao_municipal' => null,
                'email' => Sorteio::chance($pj ? 0.85 : 0.45) ? Sorteio::email($nome) : null,
                'telefone' => Sorteio::chance($pj ? 0.7 : 0.12) ? Sorteio::telefone($endereco['ddd']) : null,
                'celular' => Sorteio::chance(0.9) ? Sorteio::celular($endereco['ddd']) : null,
                'cep' => $temEndereco ? $endereco['cep'] : null, 'logradouro' => $temEndereco ? $endereco['logradouro'] : null,
                'numero' => $temEndereco ? $endereco['numero'] : null, 'complemento' => $temEndereco ? $endereco['complemento'] : null,
                'bairro' => $temEndereco ? $endereco['bairro'] : null, 'cidade' => $endereco['cidade'], 'estado' => $endereco['uf'],
                'observacoes' => Sorteio::chance(0.03) ? Sorteio::item(['Cliente prefere contato pelo WhatsApp.', 'Entregar somente após as 14h.', 'Cliente antigo, atendimento preferencial.', 'Compra para revenda.']) : null,
                'ativo' => Sorteio::chance(0.98), 'created_at' => $this->momento($dia), 'updated_at' => $this->momento(max($dia, 0)), 'deleted_at' => null,
            ]);
            $this->clientes['id'][] = $id;
            $this->clientes['dia'][] = $dia;
            $this->clientes['pj'][] = $pj;
            $this->clientes['nome'][] = $nome;

            if ($pj && $dia < 0 && count($this->contratos) < ($this->principal ? 12 : 3) && Sorteio::chance(0.08)) {
                $this->contratos[] = ['id' => $id, 'valor' => (float) Sorteio::item([290, 350, 420, 480, 590, 690, 890])];
            } elseif ($pj && $dia < 0 && $this->inquilinoId === null && Sorteio::chance(0.05)) {
                $this->inquilinoId = $id;
            }
        }
    }

    // ------------------------------------------------------------------ simulação diária

    private function simular(): void
    {
        $baseVendas = 255 * $this->escala;
        $baseOrcamentos = 28 * $this->escala;
        $baseOs = 16 * $this->escala;

        for ($d = 0; $d < $this->dias; $d++) {
            $data = $this->inicio->addDays($d);
            if ($d === 0 || $data->day === 1) {
                DB::beginTransaction();
            }

            while ($this->liberados < count($this->clientes['id']) && $this->clientes['dia'][$this->liberados] <= $d) {
                $this->liberados++;
            }

            foreach ($this->agenda[$d] ?? [] as $tarefa) {
                $tarefa();
            }
            unset($this->agenda[$d]);

            if ($data->day === 1) {
                $this->lancamentosDoMes($d, $data);
            }
            if (in_array($data->dayOfWeek, [1, 3, 5], true)) {
                $this->repor($d);
            }

            $fator = $this->fatorDoDia($d, $data);
            for ($i = Sorteio::quantidade($baseVendas * $fator); $i > 0; $i--) {
                $this->vendaBalcao($d);
            }
            for ($i = Sorteio::quantidade($baseOrcamentos * min(1.3, $fator)); $i > 0; $i--) {
                $this->orcamento($d);
            }
            if ($data->dayOfWeek !== 0) {
                for ($i = Sorteio::quantidade($baseOs * min(1.2, $fator)); $i > 0; $i--) {
                    $this->ordemServico($d);
                }
            }
            if (Sorteio::chance(0.25 * $this->escalaFinanceira())) {
                $this->despesaVariavel($d);
            }

            if ($data->isLastOfMonth() || $d === $this->hojeDia) {
                $this->gravarTudo();
                DB::commit();
                $this->console?->getOutput()->writeln(sprintf(
                    '  <comment>%s</comment> empresa %d · %s vendas · %s itens · %s movimentações',
                    $data->format('m/Y'), $this->empresaId, number_format($this->lotes['vendas']->total, 0, ',', '.'),
                    number_format($this->lotes['venda_itens']->total, 0, ',', '.'), number_format($this->lotes['movimentacoes_estoque']->total, 0, ',', '.'),
                ));
            }
        }
    }

    private function fatorDoDia(int $d, CarbonImmutable $data): float
    {
        $semana = [0 => 0.4, 1 => 0.95, 2 => 0.92, 3 => 0.95, 4 => 1.0, 5 => 1.15, 6 => 1.3][$data->dayOfWeek];
        $mes = [1 => 0.86, 2 => 0.88, 3 => 0.95, 4 => 0.95, 5 => 1.02, 6 => 1.0, 7 => 1.0, 8 => 0.97, 9 => 0.98, 10 => 1.02, 11 => 1.12, 12 => 1.45][$data->month];
        $fator = (0.82 + 0.36 * $d / $this->dias) * $semana * $mes;

        $md = $data->format('m-d');
        if (in_array($md, ['01-01', '12-25'], true)) {
            return $fator * 0.08;
        }
        if (in_array($md, ['04-21', '05-01', '08-05', '09-07', '10-12', '11-02', '11-15'], true)) {
            $fator *= 0.5;
        }
        $blackFriday = $data->setDate($data->year, 11, 1)->lastOfMonth(5);
        if (abs($data->diffInDays($blackFriday, false)) <= 3) {
            $fator *= 1.8;
        }

        return $fator;
    }

    private function vendaBalcao(int $d): void
    {
        [$clienteId, $pj] = $this->sortearCliente(0.42);
        $vendedor = Sorteio::chance(0.72) ? $this->vendedores[Sorteio::enviesado(count($this->vendedores), 1.5)] : null;
        $usuario = $vendedor['usuario'] ?? Sorteio::item($this->usuarios['vendas']);
        $quantidadeItens = (int) Sorteio::pesado([1 => 30, 2 => 22, 3 => 15, 4 => 10, 5 => 7, 6 => 5, 7 => 3, 8 => 3, 10 => 3, 14 => 1.5, 20 => 0.5]);

        $itens = [];
        $entrega = false;
        for ($i = 0; $i < $quantidadeItens; $i++) {
            $id = $this->sortearProduto();
            $produto = $this->produtos[$id];
            $quantidade = $this->quantidadeVendida($produto);
            $disponivel = $this->estoque[$id] - ($itens[$id]['qtd'] ?? 0);
            if ($disponivel < ($produto['kg'] ? 0.1 : 1)) {
                $this->faltando[$id] = true;

                continue;
            }
            $quantidade = min($quantidade, $produto['kg'] ? round($disponivel, 3) : floor($disponivel));
            $entrega = $entrega || $produto['entrega'];
            if (isset($itens[$id])) {
                $itens[$id]['qtd'] += $quantidade;
            } else {
                $itens[$id] = ['qtd' => $quantidade, 'preco' => $this->precoNoDia($id, $d), 'desconto' => 0.0];
            }
        }
        if ($itens === []) {
            return;
        }
        foreach ($itens as $id => $item) {
            if (Sorteio::chance(0.04)) {
                $itens[$id]['desconto'] = round($item['qtd'] * $item['preco'] * Sorteio::real(0.03, 0.10), 2);
            }
        }

        $forma = $this->sortearForma($clienteId === $this->consumidorId, $pj);
        $subtotal = $this->subtotal($itens);
        $desconto = Sorteio::chance(0.07) ? round($subtotal * Sorteio::real(0.02, 0.08), 2) : 0.0;
        $acrescimo = $entrega && Sorteio::chance(0.3) ? (float) Sorteio::item([15, 20, 30, 40, 60]) : 0.0;

        $this->registrarVenda($d, $clienteId, $vendedor, $usuario, $forma, $itens, $desconto, $acrescimo, null, 'balcao', $entrega ? $d + Sorteio::int(1, 4) : null);
    }

    /**
     * @param  array<int, array{qtd: float, preco: float, desconto: float}>  $itens
     * @param  array{id: int, usuario: int, percentual: float, nome: string}|null  $vendedor
     * @param  array{id: int, percentual: float, base: string, nome: string}|null  $tecnico
     */
    private function registrarVenda(
        int $d, int $clienteId, ?array $vendedor, int $usuario, string $forma, array $itens, float $desconto, float $acrescimo,
        ?string $observacoes, string $origem, ?int $entregaDia, ?array $tecnico = null,
    ): int {
        $id = $this->lotes['vendas']->reservarId();
        $numero = str_pad((string) ++$this->numVenda, 6, '0', STR_PAD_LEFT);
        $situacao = 'confirmada';
        if ($origem === 'balcao') {
            if ($d >= $this->hojeDia - 2 && Sorteio::chance(0.06)) {
                $situacao = 'em_andamento';
            } elseif (Sorteio::chance(0.035)) {
                $situacao = 'cancelada';
            }
        }
        $criado = $this->momento($d);
        $subtotal = $this->subtotal($itens);
        $total = round($subtotal - $desconto + $acrescimo, 2);
        $funcionarioId = $tecnico['id'] ?? $vendedor['id'] ?? null;

        $this->lotes['vendas']->add([
            'id' => $id, 'empresa_id' => $this->empresaId, 'cliente_id' => $clienteId, 'usuario_id' => $usuario, 'funcionario_id' => $funcionarioId,
            'numero' => $numero, 'data_venda' => $this->data($d), 'data_saida' => null, 'data_entrega' => $entregaDia !== null ? $this->data($entregaDia) : null,
            'subtotal' => $subtotal, 'desconto' => $desconto, 'acrescimo' => $acrescimo, 'total' => $total, 'forma_pagamento' => $forma,
            'forma_pagamento_id' => $this->formas[$forma]['id'], 'observacoes' => $observacoes, 'situacao' => $situacao,
            'created_at' => $criado, 'updated_at' => $situacao === 'cancelada' ? $this->momento($d, 18, 20) : $criado, 'deleted_at' => null,
        ]);

        foreach ($itens as $produtoId => $item) {
            $p = $this->produtos[$produtoId];
            $this->lotes['venda_itens']->add([
                'venda_id' => $id, 'produto_id' => $produtoId, 'produto_nome' => $p['nome'], 'produto_codigo' => $p['codigo'],
                'ncm' => $p['ncm'], 'cest' => $p['cest'], 'cfop' => $p['tipo'] === 1 ? '5102' : null, 'unidade' => $p['unidade'],
                'origem' => $p['origem'], 'csosn' => $p['icms'], 'cst_pis' => $p['tipo'] === 1 ? '07' : null, 'cst_cofins' => $p['tipo'] === 1 ? '07' : null,
                'ean' => $p['ean'], 'quantidade' => $item['qtd'], 'preco_unitario' => $item['preco'], 'desconto' => $item['desconto'],
                'total' => round($item['qtd'] * $item['preco'] - $item['desconto'], 2), 'created_at' => $criado, 'updated_at' => $criado,
            ]);
        }

        if ($situacao === 'em_andamento') {
            return $id;
        }

        foreach ($itens as $produtoId => $item) {
            if ($this->produtos[$produtoId]['controla']) {
                $this->movimentar($produtoId, 'saida', $item['qtd'], 'Venda #'.$numero, 'venda', $id, $criado, $usuario);
            }
        }

        if ($situacao === 'cancelada') {
            $estorno = $this->momento($d, 18, 20);
            foreach ($itens as $produtoId => $item) {
                if ($this->produtos[$produtoId]['controla']) {
                    $this->movimentar($produtoId, 'entrada', $item['qtd'], 'Estorno venda #'.$numero, 'venda', $id, $estorno, $usuario);
                }
            }
            if ($this->formas[$forma]['prazo']) {
                $this->contasDaVenda($id, $numero, $d, $clienteId, $forma, $total, true);
            }

            return $id;
        }

        $mes = substr($this->data($d), 0, 7);
        $this->faturamentoMes[$mes] = ($this->faturamentoMes[$mes] ?? 0) + $total;
        if (str_starts_with($forma, 'Cartão')) {
            $this->cartaoMes[$mes] = ($this->cartaoMes[$mes] ?? 0) + $total;
            $this->cartaoDia[$d] = ($this->cartaoDia[$d] ?? 0) + $total;
        } elseif ($forma === 'Pix') {
            $this->pixDia[$d] = ($this->pixDia[$d] ?? 0) + $total;
        }

        if ($this->formas[$forma]['prazo']) {
            $this->contasDaVenda($id, $numero, $d, $clienteId, $forma, $total, false);
        }

        $this->comissao($id, $numero, $d, $itens, $subtotal, $total, $vendedor, $tecnico);

        return $id;
    }

    /**
     * Mesma regra de VendaRepository::calcularComissao; comissões de meses fechados já foram pagas pela tela,
     * que grava uma conta a pagar quitada por comissão.
     *
     * @param  array<int, array{qtd: float, preco: float, desconto: float}>  $itens
     */
    private function comissao(int $vendaId, string $numero, int $d, array $itens, float $subtotal, float $total, ?array $vendedor, ?array $tecnico): void
    {
        $funcionario = $tecnico ?? $vendedor;
        $percentual = (float) ($funcionario['percentual'] ?? 0);
        if ($funcionario === null || $percentual <= 0) {
            return;
        }

        $base = $total;
        if ($tecnico !== null && $tecnico['base'] === 'servicos') {
            $servicos = 0.0;
            foreach ($itens as $produtoId => $item) {
                if ($this->produtos[$produtoId]['tipo'] === 2) {
                    $servicos += round($item['qtd'] * $item['preco'] - $item['desconto'], 2);
                }
            }
            $base = $subtotal > 0 ? $servicos * ($total / $subtotal) : 0;
        }
        $base = round($base, 2);
        if ($base <= 0) {
            return;
        }
        $valor = round($base * $percentual / 100, 2);

        $pagamentoDia = (int) $this->inicio->diffInDays($this->inicio->addDays($d)->startOfMonth()->addMonthNoOverflow()->addDays(4));
        $paga = $pagamentoDia <= $this->hojeDia && Sorteio::chance(0.96);
        $id = $this->lotes['comissoes']->reservarId();
        $this->lotes['comissoes']->add([
            'id' => $id, 'empresa_id' => $this->empresaId, 'venda_id' => $vendaId, 'funcionario_id' => $funcionario['id'],
            'percentual' => $percentual, 'base_calculo' => $base, 'valor' => $valor, 'situacao' => $paga ? 'paga' : 'pendente',
            'data_pagamento' => $paga ? $this->data($pagamentoDia) : null, 'created_at' => $this->momento($d),
            'updated_at' => $paga ? $this->momento($pagamentoDia) : $this->momento($d),
        ]);

        if ($paga) {
            $contaId = $this->lotes['contas_pagar']->reservarId();
            $quando = $this->momento($pagamentoDia, 9, 11);
            $this->lotes['contas_pagar']->add($this->linhaPagar($contaId, null, null, $this->categorias['Comissões'],
                'Comissão venda #'.$numero.' — '.$funcionario['nome'], $valor, $valor, $pagamentoDia, $pagamentoDia, null, 'paga', 'comissao_id:'.$id, $quando));
            $this->lotes['conta_pagar_baixas']->add([
                'empresa_id' => $this->empresaId, 'conta_pagar_id' => $contaId, 'valor' => $valor, 'data_pagamento' => $this->data($pagamentoDia),
                'forma_pagamento' => null, 'created_at' => $quando, 'updated_at' => $quando,
            ]);
        }
    }

    /** Como ContaReceberRepository::gerarDaVendaConfirmada (com parcelas no carnê, crediário e parte dos boletos). */
    private function contasDaVenda(int $vendaId, string $numero, int $d, int $clienteId, string $forma, float $total, bool $cancelada): void
    {
        $parcelas = match ($forma) {
            'Crediário', 'Carnê' => Sorteio::int(2, 6),
            'Boleto', 'Duplicata' => (int) Sorteio::pesado([1 => 60, 2 => 25, 3 => 15]),
            'Cheque' => Sorteio::int(1, 2),
            default => 1,
        };
        $valorParcela = floor($total / $parcelas * 100) / 100;
        for ($i = 1; $i <= $parcelas; $i++) {
            $valor = $i === $parcelas ? round($total - $valorParcela * ($parcelas - 1), 2) : $valorParcela;
            $this->contaReceber($clienteId, $vendaId, $this->categorias['Vendas'],
                'Venda #'.$numero.' — '.$forma.($parcelas > 1 ? ' ('.$i.'/'.$parcelas.')' : ''),
                $valor, $d, $d + 30 * $i, $forma, 'Gerada automaticamente na confirmação da venda.', $cancelada);
        }
    }

    private function contaReceber(?int $clienteId, ?int $vendaId, int $categoriaId, string $descricao, float $valor, int $emissao, int $vencimento,
        string $forma, ?string $observacoes, bool $cancelada = false): void
    {
        $id = $this->lotes['contas_receber']->reservarId();
        $criado = $this->momento($emissao);
        $nomeCliente = $clienteId !== null ? $this->nomeCliente($clienteId) : 'CLIENTE';
        $proxima = null;

        if ($cancelada) {
            [$situacao, $pago, $pagamento, $baixas] = ['cancelada', 0.0, null, []];
        } else {
            [$situacao, $pago, $pagamento, $baixas] = $this->liquidar(true, $valor, $emissao, $vencimento);
        }

        foreach ($baixas as [$dia, $parte]) {
            $formaBaixa = in_array($forma, self::FORMAS_BANCO, true) ? $forma : Sorteio::item(['Dinheiro', 'Pix', 'Pix', 'Cartão de Débito']);
            if ($dia >= $this->inicioExtratoDia && in_array($formaBaixa, self::FORMAS_BANCO, true) && Sorteio::chance(0.75)) {
                $formaBaixa = 'Conciliação OFX';
                $this->transacao($dia, $parte, ($forma === 'Pix' ? 'PIX RECEBIDO - ' : 'LIQUIDACAO BOLETO - ').mb_strtoupper($nomeCliente),
                    'conciliada', $id, null);
            }
            $quando = $this->momento($dia, 9, 17);
            $this->lotes['conta_receber_baixas']->add([
                'empresa_id' => $this->empresaId, 'conta_receber_id' => $id, 'valor' => $parte, 'data_pagamento' => $this->data($dia),
                'forma_pagamento' => $formaBaixa, 'created_at' => $quando, 'updated_at' => $quando,
            ]);
        }

        if (in_array($situacao, ['aberta', 'parcial'], true)) {
            // Pix que já caiu no banco e ainda espera a conciliação.
            if ($vencimento >= $this->inicioMesAtualDia && $vencimento <= $this->hojeDia && Sorteio::chance(0.35)) {
                $this->transacao(min($this->hojeDia, $vencimento + Sorteio::int(0, 2)), round($valor - $pago, 2),
                    'PIX RECEBIDO - '.mb_strtoupper($nomeCliente), 'pendente', null, null);
            }
            if ($vencimento < $this->hojeDia - 3 && $clienteId !== null && Sorteio::chance(0.65)) {
                $contato = $vencimento + Sorteio::int(3, 8);
                for ($k = Sorteio::int(1, 3); $k > 0 && $contato <= $this->hojeDia; $k--) {
                    $canal = Sorteio::item(['whatsapp', 'whatsapp', 'telefone', 'email', 'presencial']);
                    $quando = $this->momento($contato, 9, 17);
                    $this->lotes['cobrancas_historico']->add([
                        'empresa_id' => $this->empresaId, 'conta_receber_id' => $id, 'user_id' => $this->usuarios['financeiro'],
                        'canal' => $canal, 'resultado' => Sorteio::item(Catalogo::RESULTADOS_COBRANCA[$canal]),
                        'contatado_em' => $this->data($contato), 'proxima_cobranca_em' => $this->data($contato + Sorteio::int(3, 10)),
                        'created_at' => $quando, 'updated_at' => $quando,
                    ]);
                    $proxima = $contato + Sorteio::int(3, 10);
                    $contato += Sorteio::int(5, 20);
                }
            }
        }

        $atualizado = $pagamento !== null ? $this->momento($pagamento, 9, 17) : $criado;
        $this->lotes['contas_receber']->add([
            'id' => $id, 'empresa_id' => $this->empresaId, 'cliente_id' => $clienteId, 'venda_id' => $vendaId, 'categoria_id' => $categoriaId,
            'descricao' => $descricao, 'valor' => $valor, 'valor_pago' => $pago, 'data_vencimento' => $this->data($vencimento),
            'data_pagamento' => $pagamento !== null ? $this->data($pagamento) : null, 'forma_pagamento' => $forma, 'situacao' => $situacao,
            'observacoes' => $observacoes, 'created_at' => $criado, 'updated_at' => $atualizado, 'deleted_at' => null,
            'proxima_cobranca_em' => $proxima !== null ? $this->data($proxima) : null,
        ]);
    }

    private function contaPagar(?int $fornecedorId, ?int $entradaId, int $categoriaId, string $descricao, float $valor, int $emissao, int $vencimento,
        string $forma, ?string $observacoes = null): void
    {
        $id = $this->lotes['contas_pagar']->reservarId();
        [$situacao, $pago, $pagamento, $baixas] = $this->liquidar(false, $valor, $emissao, $vencimento);

        foreach ($baixas as [$dia, $parte]) {
            $formaBaixa = $forma;
            if ($dia >= $this->inicioExtratoDia && $forma !== 'Dinheiro' && Sorteio::chance(0.8)) {
                $formaBaixa = 'Conciliação OFX';
                $nome = $fornecedorId !== null ? mb_strtoupper($this->fornecedores[$fornecedorId]['nome']) : mb_strtoupper($descricao);
                $this->transacao($dia, -$parte, ($forma === 'Pix' ? 'PIX ENVIADO - ' : 'PAGAMENTO DE TITULO - ').mb_substr($nome, 0, 60), 'conciliada', null, $id);
            }
            $quando = $this->momento($dia, 9, 17);
            $this->lotes['conta_pagar_baixas']->add([
                'empresa_id' => $this->empresaId, 'conta_pagar_id' => $id, 'valor' => $parte, 'data_pagamento' => $this->data($dia),
                'forma_pagamento' => $formaBaixa, 'created_at' => $quando, 'updated_at' => $quando,
            ]);
        }

        $this->lotes['contas_pagar']->add($this->linhaPagar($id, $fornecedorId, $entradaId, $categoriaId, $descricao, $valor, $pago, $vencimento,
            $pagamento, $forma, $situacao, $observacoes, $this->momento($emissao)));
    }

    /** @return array<string, mixed> */
    private function linhaPagar(int $id, ?int $fornecedorId, ?int $entradaId, int $categoriaId, string $descricao, float $valor, float $pago,
        int $vencimento, ?int $pagamento, ?string $forma, string $situacao, ?string $observacoes, string $criado): array
    {
        return [
            'id' => $id, 'empresa_id' => $this->empresaId, 'fornecedor_id' => $fornecedorId, 'entrada_estoque_id' => $entradaId,
            'categoria_id' => $categoriaId, 'descricao' => $descricao, 'valor' => $valor, 'valor_pago' => $pago,
            'data_vencimento' => $this->data($vencimento), 'data_pagamento' => $pagamento !== null ? $this->data($pagamento) : null,
            'forma_pagamento' => $forma, 'situacao' => $situacao, 'observacoes' => $observacoes, 'created_at' => $criado,
            'updated_at' => $pagamento !== null ? $this->momento($pagamento, 9, 17) : $criado, 'deleted_at' => null,
        ];
    }

    /**
     * Quando e quanto a conta foi paga, vista de hoje.
     *
     * @return array{0: string, 1: float, 2: ?int, 3: list<array{0: int, 1: float}>}
     */
    private function liquidar(bool $receber, float $valor, int $emissao, int $vencimento): array
    {
        $dia = null;
        $parcial = false;
        if ($vencimento > $this->hojeDia) {
            if (Sorteio::chance($receber ? 0.05 : 0.02) && $emissao < $this->hojeDia) {
                $dia = Sorteio::int($emissao, $this->hojeDia);
            }
        } elseif ($receber) {
            $r = Sorteio::real(0, 1);
            $dia = match (true) {
                $r < 0.62 => max($emissao, $vencimento - Sorteio::int(0, 5)),
                $r < 0.84 => $vencimento + Sorteio::int(1, 20),
                $r < 0.90 => $vencimento + Sorteio::int(21, 120),
                $r < 0.94 => $vencimento + Sorteio::int(0, 25),
                default => null,
            };
            $parcial = $r >= 0.90 && $r < 0.94;
        } else {
            $r = Sorteio::real(0, 1);
            $dia = match (true) {
                $r < 0.90 => max($emissao, $vencimento - Sorteio::int(0, 3)),
                $r < 0.97 => $vencimento + Sorteio::int(1, 12),
                $vencimento >= $this->hojeDia - 20 => null,
                default => $vencimento + Sorteio::int(13, 40),
            };
        }

        if ($dia === null || $dia > $this->hojeDia) {
            return ['aberta', 0.0, null, []];
        }
        if ($parcial) {
            $parte = round($valor * Sorteio::real(0.3, 0.7), 2);

            return ['parcial', $parte, $dia, [[$dia, $parte]]];
        }
        if ($receber && $valor > 50 && Sorteio::chance(0.06) && $dia - 10 > $emissao) {
            $primeira = round($valor * Sorteio::real(0.3, 0.6), 2);

            return ['paga', $valor, $dia, [[$dia - 10, $primeira], [$dia, round($valor - $primeira, 2)]]];
        }

        return ['paga', $valor, $dia, [[$dia, $valor]]];
    }

    private function orcamento(int $d): void
    {
        [$clienteId, $pj] = $this->sortearCliente(0.12, 3.0);
        $usuario = Sorteio::item($this->usuarios['atendimento']);
        $itens = [];
        for ($i = (int) Sorteio::pesado([2 => 20, 3 => 20, 4 => 15, 5 => 12, 6 => 10, 8 => 10, 10 => 8, 14 => 5]); $i > 0; $i--) {
            $id = $this->sortearProduto();
            $produto = $this->produtos[$id];
            $quantidade = $this->quantidadeVendida($produto) * ($produto['kg'] ? 1 : Sorteio::int(1, 3));
            $itens[$id] ??= ['qtd' => 0.0, 'preco' => $this->precoNoDia($id, $d), 'desconto' => 0.0];
            $itens[$id]['qtd'] += $quantidade;
        }
        if (Sorteio::chance(0.15)) {
            $servico = $this->servicos[Sorteio::item(['Instalação de TV na parede', 'Instalação de ar-condicionado 9.000 a 12.000 BTUs', 'Montagem de móveis (hora)', 'Entrega e instalação de eletrodoméstico'])];
            $itens[$servico] = ['qtd' => 1.0, 'preco' => $this->precoNoDia($servico, $d), 'desconto' => 0.0];
        }
        $subtotal = $this->subtotal($itens);
        $desconto = Sorteio::chance(0.2) ? round($subtotal * Sorteio::real(0.03, 0.10), 2) : 0.0;
        $forma = (string) Sorteio::pesado($pj
            ? ['Boleto' => 35, 'Duplicata' => 20, 'Pix' => 25, 'Transferência' => 10, 'Cartão de Crédito' => 10]
            : ['Pix' => 30, 'Cartão de Crédito' => 30, 'Dinheiro' => 10, 'Crediário' => 15, 'Boleto' => 10, 'Carnê' => 5]);
        $observacoes = Sorteio::chance(0.3) ? Sorteio::item(['Validade da proposta: 7 dias.', 'Entrega combinada após aprovação.',
            'Valores sujeitos à disponibilidade de estoque.', 'Pagamento no Pix com 5% de desconto já aplicado.', 'Cliente vai confirmar com o sócio.']) : null;

        $idade = $this->hojeDia - $d;
        $r = Sorteio::real(0, 1);
        if ($idade <= 7) {
            $situacao = $r < 0.65 ? 'pendente' : ($r < 0.9 ? 'aprovado' : 'recusado');
        } else {
            $situacao = $r < 0.45 ? 'aprovado' : ($r < 0.73 ? 'recusado' : ($r < 0.79 ? 'cancelado' : 'pendente'));
        }
        $decisao = min($this->hojeDia, $d + Sorteio::int(0, 7));

        $id = $this->lotes['orcamentos']->reservarId();
        $numero = str_pad((string) ++$this->numOrcamento, 6, '0', STR_PAD_LEFT);
        $linha = [
            'id' => $id, 'empresa_id' => $this->empresaId, 'cliente_id' => $clienteId, 'usuario_id' => $usuario, 'venda_id' => null,
            'ordem_servico_id' => null, 'numero' => $numero, 'data_orcamento' => $this->data($d),
            'data_entrega' => Sorteio::chance(0.5) ? $this->data($d + Sorteio::int(3, 15)) : null, 'subtotal' => $subtotal,
            'desconto' => $desconto, 'acrescimo' => 0.0, 'total' => round($subtotal - $desconto, 2), 'forma_pagamento' => $forma,
            'observacoes' => $observacoes, 'situacao' => $situacao, 'created_at' => $this->momento($d),
            'updated_at' => $situacao === 'pendente' ? $this->momento($d) : $this->momento($decisao), 'deleted_at' => null,
        ];

        $gravar = function (?int $vendaId) use ($linha, $itens, $d): void {
            $linha['venda_id'] = $vendaId;
            $this->lotes['orcamentos']->add($linha);
            foreach ($itens as $produtoId => $item) {
                $this->lotes['orcamento_itens']->add([
                    'orcamento_id' => $linha['id'], 'produto_id' => $produtoId, 'produto_nome' => $this->produtos[$produtoId]['nome'],
                    'produto_codigo' => $this->produtos[$produtoId]['codigo'], 'quantidade' => $item['qtd'], 'preco_unitario' => $item['preco'],
                    'desconto' => $item['desconto'], 'total' => round($item['qtd'] * $item['preco'] - $item['desconto'], 2),
                    'created_at' => $this->momento($d), 'updated_at' => $this->momento($d),
                ]);
            }
        };

        if ($situacao !== 'aprovado') {
            $gravar(null);

            return;
        }

        // A aprovação vira venda no dia (OrcamentoService::aprovar); falta de estoque é comprada antes de confirmar.
        $aprovar = function () use ($decisao, $clienteId, $usuario, $forma, $itens, $desconto, $observacoes, $gravar): void {
            foreach ($itens as $produtoId => $item) {
                $this->garantirEstoque($produtoId, $item['qtd'], $decisao);
            }
            $gravar($this->registrarVenda($decisao, $clienteId, null, $usuario, $forma, $itens, $desconto, 0.0, $observacoes, 'orcamento', null));
        };
        if ($decisao <= $d) {
            $aprovar();
        } else {
            $this->agenda[$decisao][] = $aprovar;
        }
    }

    private function ordemServico(int $d): void
    {
        $tipo = (string) Sorteio::pesado(array_map(fn ($e) => $e['peso'], Catalogo::EQUIPAMENTOS));
        $equipamento = Catalogo::EQUIPAMENTOS[$tipo];
        [$nomeAparelho, $marca, $modelo] = Sorteio::item($equipamento['aparelhos']);
        [$relato, $diagnostico, $executado, $servico, $prefixoPeca] = Sorteio::item($equipamento['defeitos']);
        [$clienteId] = $this->sortearCliente(0.0);
        $tecnico = $this->tecnicos !== [] && Sorteio::chance(0.9) ? Sorteio::item($this->tecnicos) : null;
        $usuario = Sorteio::item($this->usuarios['atendimento']);

        $itens = [];
        $servicoId = $this->servicos[$servico];
        $itens[$servicoId] = ['qtd' => 1.0, 'preco' => $this->precoNoDia($servicoId, $d), 'desconto' => 0.0];
        $pecaId = $prefixoPeca !== null ? $this->peca($prefixoPeca, $marca, $modelo) : null;
        if ($pecaId !== null) {
            $itens[$pecaId] = ['qtd' => 1.0, 'preco' => $this->precoNoDia($pecaId, $d), 'desconto' => 0.0];
        }
        $subtotal = $this->subtotal($itens);
        $desconto = Sorteio::chance(0.1) ? round($subtotal * 0.05, 2) : 0.0;
        $forma = (string) Sorteio::pesado(['Pix' => 35, 'Dinheiro' => 20, 'Cartão de Crédito' => 30, 'Cartão de Débito' => 15]);

        $conclusao = $d + Sorteio::int(1, $pecaId !== null ? 12 : 6);
        $faturamento = $conclusao + Sorteio::int(0, 4);
        $r = Sorteio::real(0, 1);
        if ($conclusao > $this->hojeDia) {
            $situacao = $pecaId !== null && $r < 0.3 ? 'aguardando_peca' : ($r < 0.6 ? 'em_execucao' : 'aberta');
        } elseif ($r < 0.08) {
            $situacao = 'cancelada';
        } elseif ($faturamento > $this->hojeDia || $r < 0.11) {
            $situacao = 'concluida';
        } else {
            $situacao = 'faturada';
        }
        $concluida = in_array($situacao, ['concluida', 'faturada'], true);

        $id = $this->lotes['ordens_servico']->reservarId();
        $numero = str_pad((string) ++$this->numOs, 6, '0', STR_PAD_LEFT);
        $linha = [
            'id' => $id, 'empresa_id' => $this->empresaId, 'cliente_id' => $clienteId, 'usuario_id' => $usuario, 'funcionario_id' => $tecnico['id'] ?? null,
            'orcamento_id' => null, 'venda_id' => null, 'numero' => $numero, 'data_abertura' => $this->data($d),
            'data_previsao' => $this->data($d + Sorteio::int(2, 7)), 'data_conclusao' => $concluida ? $this->data($conclusao) : null,
            'equipamento' => $nomeAparelho, 'marca' => $marca, 'modelo' => $modelo,
            'numero_serie' => $tipo === 'celular' ? '35'.Sorteio::int(1000000, 9999999).Sorteio::int(100000, 999999) : strtoupper(base_convert((string) Sorteio::int(100000000, 999999999), 10, 36)),
            'acessorios' => Sorteio::item($equipamento['acessorios']), 'defeito_relatado' => $relato,
            'diagnostico' => $situacao === 'aberta' ? null : ($situacao === 'cancelada' ? 'Cliente não aprovou o orçamento do reparo.' : $diagnostico),
            'servico_executado' => $concluida ? $executado : null, 'garantia' => $concluida ? '90 dias' : null,
            'subtotal' => $subtotal, 'desconto' => $desconto, 'acrescimo' => 0.0, 'total' => round($subtotal - $desconto, 2),
            'forma_pagamento' => $forma, 'observacoes' => Sorteio::chance(0.15) ? Sorteio::item(['Cliente autorizou o reparo por telefone.', 'Aparelho com senha informada pelo cliente.', 'Avisar pelo WhatsApp quando ficar pronto.']) : null,
            'situacao' => $situacao, 'created_at' => $this->momento($d), 'updated_at' => $this->momento(min($this->hojeDia, $concluida ? $conclusao : $d)), 'deleted_at' => null,
        ];

        $gravar = function (?int $vendaId) use ($linha, $itens, $d): void {
            $linha['venda_id'] = $vendaId;
            $this->lotes['ordens_servico']->add($linha);
            foreach ($itens as $produtoId => $item) {
                $this->lotes['ordem_servico_itens']->add([
                    'ordem_servico_id' => $linha['id'], 'produto_id' => $produtoId, 'produto_nome' => $this->produtos[$produtoId]['nome'],
                    'produto_codigo' => $this->produtos[$produtoId]['codigo'], 'quantidade' => $item['qtd'], 'preco_unitario' => $item['preco'],
                    'desconto' => $item['desconto'], 'total' => round($item['qtd'] * $item['preco'] - $item['desconto'], 2),
                    'created_at' => $this->momento($d), 'updated_at' => $this->momento($d),
                ]);
            }
        };

        if ($situacao !== 'faturada') {
            $gravar(null);

            return;
        }

        // Faturamento da OS (OrdemServicoService): venda com os itens da OS, comissão do técnico pela regra de OS.
        $this->agenda[$faturamento][] = function () use ($faturamento, $clienteId, $usuario, $forma, $itens, $desconto, $numero, $nomeAparelho, $tecnico, $pecaId, $gravar): void {
            if ($pecaId !== null) {
                $this->garantirEstoque($pecaId, 1.0, $faturamento);
            }
            $gravar($this->registrarVenda($faturamento, $clienteId, null, $usuario, $forma, $itens, $desconto, 0.0,
                'OS #'.$numero.' - '.$nomeAparelho, 'os', null, $tecnico));
        };
    }

    private function peca(string $prefixo, string $marca, string $modelo): ?int
    {
        $alvos = [$modelo, $marca.' '.$modelo];
        if (preg_match('/(\d+)"/', $modelo, $polegadas)) {
            $alvos[] = $marca.' '.$polegadas[1].'"';
            $alvos[] = $polegadas[1].'"';
        }
        $candidatas = [];
        foreach ($this->pecas as $nome => $id) {
            if (! str_starts_with($nome, $prefixo.' ')) {
                continue;
            }
            foreach ($alvos as $alvo) {
                if (str_ends_with($nome, ' '.$alvo)) {
                    return $id;
                }
            }
            $candidatas[] = $id;
        }

        return in_array($prefixo, ['Tela de Notebook 15,6" LED', 'SSD'], true) && $candidatas !== [] ? Sorteio::item($candidatas) : null;
    }

    // ------------------------------------------------------------------ compras e estoque

    private function repor(int $d): void
    {
        $porFornecedor = [];
        foreach (array_keys($this->faltando) as $produtoId) {
            if (! isset($this->emReposicao[$produtoId]) && $this->estoque[$produtoId] <= $this->produtos[$produtoId]['minimo']) {
                $porFornecedor[$this->produtos[$produtoId]['fornecedor']][] = $produtoId;
            }
        }
        $this->faltando = [];

        foreach ($porFornecedor as $fornecedorId => $produtos) {
            $quantidades = [];
            foreach ($produtos as $produtoId) {
                $quantidades[$produtoId] = $this->quantidadeCompra($produtoId);
            }
            if (Sorteio::chance(0.35)) {
                $this->pedidoCompra($d, $fornecedorId, $quantidades);
            } else {
                $this->entrada($d, $fornecedorId, $quantidades, null);
            }
        }
    }

    private function quantidadeCompra(int $produtoId): float
    {
        $p = $this->produtos[$produtoId];
        $falta = max($p['maximo'] - $this->estoque[$produtoId], $p['minimo']);
        if ($p['kg']) {
            return ceil($falta / 5) * 5;
        }

        return $p['preco'] < 30 ? ceil($falta / 6) * 6 : ceil($falta);
    }

    private function garantirEstoque(int $produtoId, float $quantidade, int $d): void
    {
        $p = $this->produtos[$produtoId];
        if ($p['controla'] && $this->estoque[$produtoId] < $quantidade) {
            $this->entrada($d, $p['fornecedor'], [$produtoId => max($quantidade - $this->estoque[$produtoId], $this->quantidadeCompra($produtoId))], null);
        }
    }

    /** @param  array<int, float>  $quantidades */
    private function pedidoCompra(int $d, int $fornecedorId, array $quantidades): void
    {
        $id = $this->lotes['pedidos_compra']->reservarId();
        $sequencia = ++$this->numPedido;
        $chegada = $d + Sorteio::int(2, 6);
        $r = Sorteio::real(0, 1);
        $situacao = match (true) {
            $r < 0.04 => 'cancelado',
            $chegada > $this->hojeDia => $r < 0.2 ? 'rascunho' : 'aprovado',
            $r < 0.14 => 'parcial',
            default => 'recebido',
        };
        $recebidas = [];
        $total = 0.0;
        foreach ($quantidades as $produtoId => $quantidade) {
            $custo = $this->custoNoDia($produtoId, $d);
            $recebida = match ($situacao) {
                'recebido' => $quantidade,
                'parcial' => $this->produtos[$produtoId]['kg'] ? round($quantidade * Sorteio::real(0.5, 0.9), 1) : floor($quantidade * Sorteio::real(0.5, 0.9)),
                default => 0.0,
            };
            if ($recebida > 0) {
                $recebidas[$produtoId] = $recebida;
            }
            $total += round($quantidade * $custo, 2);
            $this->lotes['pedido_compra_itens']->add([
                'pedido_compra_id' => $id, 'produto_id' => $produtoId, 'produto_nome' => $this->produtos[$produtoId]['nome'],
                'produto_codigo' => $this->produtos[$produtoId]['codigo'], 'quantidade' => $quantidade, 'quantidade_recebida' => $recebida,
                'preco_unitario' => $custo, 'desconto' => 0.0, 'total' => round($quantidade * $custo, 2),
                'created_at' => $this->momento($d), 'updated_at' => $this->momento($d),
            ]);
            if ($situacao !== 'cancelado') {
                $this->emReposicao[$produtoId] = true;
            }
        }

        $this->lotes['pedidos_compra']->add([
            'id' => $id, 'empresa_id' => $this->empresaId, 'fornecedor_id' => $fornecedorId, 'numero_seq' => $sequencia,
            'numero' => 'PC-'.str_pad((string) $sequencia, 6, '0', STR_PAD_LEFT), 'data_emissao' => $this->data($d),
            'data_prevista' => $this->data($chegada + Sorteio::int(0, 2)), 'subtotal' => round($total, 2), 'desconto' => 0.0, 'total' => round($total, 2),
            'observacoes' => Sorteio::chance(0.2) ? 'Entregar no depósito pela manhã.' : null, 'situacao' => $situacao,
            'created_at' => $this->momento($d), 'updated_at' => $this->momento(min($this->hojeDia, $chegada)), 'deleted_at' => null,
        ]);

        if ($recebidas !== []) {
            $this->agenda[$chegada][] = fn () => $this->entrada($chegada, $fornecedorId, $recebidas, $id);
        }
    }

    /**
     * Entrada confirmada (EntradaEstoqueRepository::confirmar): soma ao estoque, atualiza o custo e gera a conta a pagar.
     *
     * @param  array<int, float>  $quantidades
     */
    private function entrada(int $d, int $fornecedorId, array $quantidades, ?int $pedidoId): void
    {
        $id = $this->lotes['entradas_estoque']->reservarId();
        $this->numNota += Sorteio::int(1, 40);
        $nota = (string) $this->numNota;
        $criado = $this->momento($d, 8, 11);
        $comChave = Sorteio::chance(0.45);
        $total = 0.0;

        foreach ($quantidades as $produtoId => $quantidade) {
            $custo = $this->custoNoDia($produtoId, $d);
            $total += round($quantidade * $custo, 2);
            $this->lotes['entrada_estoque_itens']->add([
                'entrada_estoque_id' => $id, 'produto_id' => $produtoId, 'produto_nome' => $this->produtos[$produtoId]['nome'],
                'produto_codigo' => $this->produtos[$produtoId]['codigo'], 'quantidade' => $quantidade, 'preco_unitario' => $custo,
                'desconto' => 0.0, 'total' => round($quantidade * $custo, 2), 'created_at' => $criado, 'updated_at' => $criado,
            ]);
            $this->movimentar($produtoId, 'entrada', $quantidade, 'Entrada #'.$nota, 'entrada_estoque', $id, $criado, $this->usuarios['estoque']);
            unset($this->emReposicao[$produtoId], $this->faltando[$produtoId]);
        }
        $total = round($total, 2);
        $fornecedor = $this->fornecedores[$fornecedorId];

        $this->lotes['entradas_estoque']->add([
            'id' => $id, 'empresa_id' => $this->empresaId, 'fornecedor_id' => $fornecedorId, 'pedido_compra_id' => $pedidoId, 'numero' => $nota,
            'data_entrada' => $this->data($d), 'subtotal' => $total, 'desconto' => 0.0, 'total' => $total, 'valor_nota' => $total,
            'observacoes' => null, 'situacao' => 'confirmada',
            'chave_acesso' => $comChave ? Sorteio::chaveNfe($fornecedor['uf'], $fornecedor['cnpj'], $this->inicio->addDays($d)->format('ym'), (int) $nota) : null,
            'xml_importado' => null, 'numero_nfe' => $comChave ? $nota : null, 'serie_nfe' => $comChave ? '1' : null,
            'data_emissao_nfe' => $comChave ? $this->data($d - Sorteio::int(1, 3)) : null, 'created_at' => $criado, 'updated_at' => $criado, 'deleted_at' => null,
        ]);

        $this->contaPagar($fornecedorId, $id, $this->categorias['Compras / Estoque'], 'Entrada #'.$nota.' — '.$fornecedor['nome'],
            $total, $d, $d + 30, Sorteio::item(['Boleto', 'Boleto', 'Boleto', 'Pix', 'Transferência']));
    }

    private function movimentar(int $produtoId, string $tipo, float $quantidade, string $motivo, string $origem, int $origemId, string $quando, int $usuario): void
    {
        $antes = round($this->estoque[$produtoId], 3);
        $depois = round($tipo === 'saida' ? $antes - $quantidade : $antes + $quantidade, 3);
        $this->estoque[$produtoId] = $depois;
        if ($tipo === 'saida' && $depois <= $this->produtos[$produtoId]['minimo']) {
            $this->faltando[$produtoId] = true;
        }

        $this->lotes['movimentacoes_estoque']->add([
            'empresa_id' => $this->empresaId, 'produto_id' => $produtoId, 'user_id' => $usuario, 'tipo' => $tipo, 'quantidade' => $quantidade,
            'estoque_antes' => $antes, 'estoque_depois' => $depois, 'motivo' => $motivo, 'origem' => $origem, 'origem_id' => $origemId,
            'created_at' => $quando, 'updated_at' => $quando,
        ]);
    }

    // ------------------------------------------------------------------ financeiro recorrente

    private function lancamentosDoMes(int $d, CarbonImmutable $data): void
    {
        $anterior = $data->subMonthNoOverflow()->format('Y-m');
        $competencia = $data->subMonthNoOverflow()->format('m/Y');
        $mesAno = $data->format('m/Y');
        $anos = ($this->hojeDia - $d) / 365;
        $fator = $this->principal ? 1.0 : 0.35;
        $vence = fn (int $dia) => $d + min($dia, $data->daysInMonth) - 1;

        foreach (Catalogo::DESPESAS_FIXAS as [$descricao, $categoria, $fornecedor, $dia, $minimo, $maximo]) {
            $valor = $descricao === 'Taxas da maquininha de cartão' && isset($this->cartaoMes[$anterior])
                ? $this->cartaoMes[$anterior] * 0.023
                : Sorteio::real($minimo, $maximo) * $fator;
            if (str_starts_with($descricao, 'Aluguel')) {
                $valor = $minimo * $fator * (1 - 0.045 * floor($anos));
            }
            $this->contaPagar($this->fornecedoresDespesa[$fornecedor], null, $this->categorias[$categoria], $descricao.' - '.$mesAno,
                round($valor, 2), $d, $vence($dia), Sorteio::item(['Boleto', 'Boleto', 'Débito automático', 'Pix']));
        }

        $folha = $this->folha * (1 - 0.06 * floor($anos));
        $this->contaPagar(null, null, $this->categorias['Folha de pagamento'], 'Folha de pagamento - competência '.$competencia, round($folha, 2), $d, $vence(5), 'Transferência');
        $this->contaPagar(null, null, $this->categorias['Encargos trabalhistas'], 'FGTS - competência '.$competencia, round($folha * 0.08, 2), $d, $vence(7), 'Boleto');
        $this->contaPagar(null, null, $this->categorias['Encargos trabalhistas'], 'GPS - INSS retido - competência '.$competencia, round($folha * 0.09, 2), $d, $vence(20), 'Boleto');
        if (isset($this->faturamentoMes[$anterior])) {
            $this->contaPagar(null, null, $this->categorias['Impostos'], 'DAS Simples Nacional - competência '.$competencia,
                round($this->faturamentoMes[$anterior] * 0.073, 2), $d, $vence(20), 'Boleto');
        }

        foreach ($this->contratos as $contrato) {
            $this->contaReceber($contrato['id'], null, $this->categorias['Contratos de manutenção'], 'Contrato de manutenção mensal - '.$mesAno,
                $contrato['valor'], $d, $vence(10), 'Boleto', null);
        }
        if ($this->inquilinoId !== null) {
            $this->contaReceber($this->inquilinoId, null, $this->categorias['Aluguéis recebidos'], 'Aluguel da sala 02 - '.$mesAno,
                round(1800 * (1 - 0.045 * floor($anos)), 2), $d, $vence(10), 'Pix', null);
        }
    }

    private function despesaVariavel(int $d): void
    {
        [$descricao, $categoria, $minimo, $maximo] = Sorteio::item([
            ['Frete de mercadorias', 'Fretes', 80, 650], ['Manutenção do ar-condicionado da loja', 'Manutenção', 180, 900],
            ['Conserto da porta de enrolar', 'Manutenção', 250, 1200], ['Combustível - entregas', 'Fretes', 120, 450],
            ['Impressão de panfletos', 'Marketing', 200, 800], ['Material de limpeza da loja', 'Material de escritório', 60, 250],
            ['Bobinas para impressora térmica', 'Material de escritório', 90, 260], ['Uniformes da equipe', 'Material de escritório', 300, 1100],
        ]);
        $fornecedor = Sorteio::item($this->fornecedoresPorDepto['Diversos'] ?? [null]);
        $this->contaPagar($fornecedor, null, $this->categorias[$categoria], $descricao, round(Sorteio::real($minimo, $maximo) * ($this->principal ? 1 : 0.4), 2),
            $d, $d + Sorteio::int(0, 15), Sorteio::item(['Pix', 'Boleto', 'Dinheiro']));
    }

    private function escalaFinanceira(): float
    {
        return $this->principal ? 1.0 : 0.4;
    }

    // ------------------------------------------------------------------ banco (OFX)

    private function transacao(int $d, float $valor, string $descricao, string $situacao, ?int $receberId, ?int $pagarId, ?string $motivo = null): void
    {
        $fitid = sprintf('%s%06d', $this->inicio->addDays($d)->format('Ymd'), ++$this->fitid);
        $data = $this->data($d);
        $conciliado = $situacao === 'conciliada' ? $this->momento(min($this->hojeDia, $d + Sorteio::int(1, 3)), 9, 17) : null;
        $this->transacoes[substr($data, 0, 7)][] = [
            'empresa_id' => $this->empresaId, 'extrato_bancario_id' => 0,
            'fingerprint' => hash('sha256', $this->empresaId.'|001|67890-1|'.$fitid.'|'.$data.'|'.number_format($valor, 2, '.', '')),
            'fitid' => $fitid, 'data_transacao' => $data, 'tipo' => $valor >= 0 ? 'CREDIT' : 'DEBIT', 'valor' => round($valor, 2),
            'descricao' => mb_substr($descricao, 0, 255), 'memo' => null, 'situacao' => $situacao, 'conta_receber_id' => $receberId,
            'conta_pagar_id' => $pagarId, 'conciliado_em' => $conciliado, 'motivo_ignorado' => $motivo, 'created_at' => null, 'updated_at' => null,
        ];
    }

    private function extratos(): void
    {
        // Créditos das vendas à vista que o caixa já lançou: ignorados nos meses fechados, pendentes no mês atual.
        for ($d = max(1, $this->inicioExtratoDia); $d <= $this->hojeDia; $d++) {
            $fechado = $d < $this->inicioMesAtualDia;
            foreach ([['cartao', 'REDE - CREDITO DE VENDAS CARTAO', 0.975], ['pix', 'PIX RECEBIDOS - VENDAS NO BALCAO', 1.0]] as [$tipo, $descricao, $fator]) {
                $valor = round((($tipo === 'cartao' ? $this->cartaoDia : $this->pixDia)[$d - 1] ?? 0) * $fator, 2);
                if ($valor > 0) {
                    $this->transacao($d, $valor, $descricao, $fechado ? 'ignorada' : 'pendente', null, null,
                        $fechado ? 'Vendas à vista já lançadas no caixa.' : null);
                }
            }
        }

        ksort($this->transacoes);
        foreach ($this->transacoes as $mes => $linhas) {
            $inicio = CarbonImmutable::parse($mes.'-01');
            $fim = $inicio->endOfMonth()->min($this->inicio->addDays($this->hojeDia));
            $importado = $fim->addDay()->min($this->inicio->addDays($this->hojeDia))->setTime(8, 30)->format('Y-m-d H:i:s');
            $id = $this->lotes['extratos_bancarios']->reservarId();
            $this->lotes['extratos_bancarios']->add([
                'id' => $id, 'empresa_id' => $this->empresaId, 'arquivo_nome' => 'extrato-bb-'.$mes.'.ofx',
                'arquivo_hash' => hash('sha256', 'extrato|'.$this->empresaId.'|'.$mes), 'conta_bancaria' => '001 | 67890-1',
                'data_inicio' => $inicio->toDateString(), 'data_fim' => $fim->toDateString(), 'created_at' => $importado, 'updated_at' => $importado,
            ]);
            usort($linhas, fn ($a, $b) => strcmp($a['data_transacao'].$a['fitid'], $b['data_transacao'].$b['fitid']));
            foreach ($linhas as $linha) {
                $linha['extrato_bancario_id'] = $id;
                $linha['created_at'] = $importado;
                $linha['updated_at'] = $linha['conciliado_em'] ?? $importado;
                if ($linha['conciliado_em'] !== null && $linha['conciliado_em'] < $importado) {
                    $linha['conciliado_em'] = $importado;
                    $linha['updated_at'] = $importado;
                }
                $this->lotes['transacoes_bancarias']->add($linha);
            }
        }
    }

    // ------------------------------------------------------------------ fechamento

    private function finalizar(): void
    {
        DB::transaction(function (): void {
            foreach ($this->produtos as $id => $produto) {
                $linha = $produto['linha'];
                $linha['estoque_atual'] = $produto['controla'] ? round($this->estoque[$id], 3) : 0;
                $this->lotes['produtos']->add($linha);
            }
            $this->extratos();
            $this->gravarTudo();
        });
    }

    private function gravarTudo(): void
    {
        foreach ($this->lotes as $lote) {
            $lote->gravar();
        }
    }

    // ------------------------------------------------------------------ apoio

    /** @return array{0: int, 1: bool} */
    private function sortearCliente(float $chanceConsumidor, float $pesoPj = 1.0): array
    {
        if ($this->liberados === 0 || Sorteio::chance($chanceConsumidor)) {
            return [$this->consumidorId, false];
        }
        for ($tentativa = 0; $tentativa < 3; $tentativa++) {
            $i = Sorteio::chance(0.55) ? Sorteio::int(0, $this->liberados - 1) : Sorteio::enviesado($this->liberados, 1.8);
            if ($this->clientes['pj'][$i] || $pesoPj <= 1.0 || $tentativa === 2 || ! Sorteio::chance(1 - 1 / $pesoPj)) {
                return [$this->clientes['id'][$i], $this->clientes['pj'][$i]];
            }
        }

        return [$this->consumidorId, false];
    }

    private function nomeCliente(int $id): string
    {
        if ($this->clientes['id'] === []) {
            return 'CLIENTE';
        }
        $i = $id - $this->clientes['id'][0];

        return $this->clientes['nome'][$i] ?? 'CLIENTE';
    }

    private function sortearForma(bool $consumidor, bool $pj): string
    {
        $pesos = ['Pix' => 34, 'Dinheiro' => 22, 'Cartão de Débito' => 18, 'Cartão de Crédito' => 26];
        if (! $consumidor) {
            $pesos += ['Boleto' => $pj ? 30 : 6, 'Crediário' => $pj ? 0 : 7, 'Duplicata' => $pj ? 20 : 0, 'Carnê' => 2, 'A Prazo' => 2, 'Cheque' => 1, 'Transferência' => 2];
        }

        return (string) Sorteio::pesado($pesos);
    }

    private function sortearProduto(): int
    {
        $alvo = Sorteio::real(0, end($this->acumulado));
        $baixo = 0;
        $alto = count($this->acumulado) - 1;
        while ($baixo < $alto) {
            $meio = intdiv($baixo + $alto, 2);
            if ($this->acumulado[$meio] < $alvo) {
                $baixo = $meio + 1;
            } else {
                $alto = $meio;
            }
        }

        return $this->acumuladoIds[$baixo];
    }

    /** @param  array<string, mixed>  $produto */
    private function quantidadeVendida(array $produto): float
    {
        [$minimo, $maximo] = $produto['qtd'];
        if ($produto['kg']) {
            return round(Sorteio::real($minimo, $maximo), 3);
        }

        return (float) ($minimo + Sorteio::enviesado((int) $maximo - (int) $minimo + 1, 2.2));
    }

    /** @param  array<int, array{qtd: float, preco: float, desconto: float}>  $itens */
    private function subtotal(array $itens): float
    {
        $soma = 0.0;
        foreach ($itens as $item) {
            $soma += round($item['qtd'] * $item['preco'] - $item['desconto'], 2);
        }

        return round($soma, 2);
    }

    /** O preço de hoje, descontada a inflação (5,5% ao ano) para vendas antigas. */
    private function precoNoDia(int $produtoId, int $d): float
    {
        $preco = $this->produtos[$produtoId]['preco'];

        return $d >= $this->hojeDia - 30 ? $preco : self::arredondarPreco($preco * (1 - 0.055 * ($this->hojeDia - $d) / 365));
    }

    private function custoNoDia(int $produtoId, int $d): float
    {
        return round($this->produtos[$produtoId]['custo'] * (1 - 0.055 * ($this->hojeDia - $d) / 365), 2);
    }

    private static function arredondarPreco(float $preco): float
    {
        // Preço de prateleira: 1.099,00 · 149,90 · 6,49.
        if ($preco >= 1000) {
            return round($preco / 10) * 10 - 1;
        }
        if ($preco >= 100) {
            return round(round($preco) - 0.10, 2);
        }

        return max(0.99, round(round($preco, 1) - 0.01, 2));
    }

    /** @return array{cep: string, logradouro: string, numero: string, complemento: ?string, bairro: string, cidade: string, uf: string, ddd: string} */
    private function endereco(): array
    {
        static $pesos = null;
        $pesos ??= array_map(fn ($c) => $c[4], Catalogo::CIDADES);
        [$cidade, $uf, $ddd, [$cepMin, $cepMax], , $bairros] = Catalogo::CIDADES[(int) Sorteio::pesado($pesos)];

        return [
            'cep' => sprintf('%05d-%03d', Sorteio::int($cepMin, $cepMax), Sorteio::int(0, 999)), 'logradouro' => Sorteio::item(Catalogo::LOGRADOUROS),
            'numero' => Sorteio::chance(0.04) ? 'S/N' : (string) Sorteio::int(1, 3500), 'complemento' => Sorteio::chance(0.25) ? Sorteio::item(Catalogo::COMPLEMENTOS) : null,
            'bairro' => Sorteio::item($bairros), 'cidade' => $cidade, 'uf' => $uf, 'ddd' => $ddd,
        ];
    }

    private function data(int $d): string
    {
        return $this->datas[$d] ??= $this->inicio->addDays($d)->toDateString();
    }

    private function momento(int $d, int $horaMin = 8, int $horaMax = 19): string
    {
        return $this->data($d).sprintf(' %02d:%02d:%02d', Sorteio::int($horaMin, $horaMax), Sorteio::int(0, 59), Sorteio::int(0, 59));
    }
}
