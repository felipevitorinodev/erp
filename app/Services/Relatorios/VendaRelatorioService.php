<?php

namespace App\Services\Relatorios;

use App\Models\Clientes\Cliente;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Funcionarios\Funcionario;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\PdfRelatorio;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use App\Repositories\Relatorios\VendaRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendaRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected VendaRelatorioRepository $repository
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $totais = $this->repository->totais($this->repository->consulta($empresaId, $filtros['periodo'], $filtros));
        $anterior = $this->repository->totais($this->repository->consulta($empresaId, $filtros['periodo']->anterior(), $filtros));
        $vendas = $this->repository->ordenar(
            $this->repository->consulta($empresaId, $filtros['periodo'], $filtros)->with('cliente:id,nome'),
            $filtros
        )->paginate(20, ['*'], 'page', null, $totais['registros'])->withQueryString();

        return view('relatorios.consulta', array_merge(
            $this->shell('vendas', $filtros, true, ['situacao', 'forma_pagamento', 'busca', 'cliente_id', 'funcionario_id']),
            [
                'parcial' => 'relatorios.parcial.vendas',
                'totais' => $totais,
                'vendas' => $vendas,
                'kpis' => $this->kpis($totais, $anterior),
                'campos' => $this->campos($empresaId, $filtros),
            ]
        ));
    }

    /**
     * @return array{graficos: list<array<string, mixed>>}
     */
    public function resumo(Request $request): array
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $periodo = $filtros['periodo'];
        $consulta = $this->repository->consulta($empresaId, $periodo, $filtros);
        $serie = SerieTemporal::preencher($periodo, $this->repository->serie($consulta, $periodo, $filtros));
        $formas = $this->repository->formas($this->repository->consulta($empresaId, $periodo, $filtros), $filtros);
        $titulo = $filtros['situacao'] === '' ? 'Faturamento das confirmadas' : 'Valor no período';

        return [
            'graficos' => [
                $this->graficoLinha('evolucao', $titulo, $serie, 'Valor'),
                $this->graficoBarras('formas', 'Formas de pagamento', $formas, 'moeda'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $totais = $this->repository->totais($this->repository->consulta($empresaId, $filtros['periodo'], $filtros));
        $vendas = $this->repository->ordenar(
            $this->repository->consulta($empresaId, $filtros['periodo'], $filtros)->with('cliente:id,nome'),
            $filtros
        )->paginate(20, ['*'], 'page', null, $totais['registros']);

        return [
            'total' => $vendas->total(),
            'totais' => $totais,
            'linhas' => $vendas->getCollection()->map(fn ($venda) => [
                'numero' => $venda->numero,
                'cliente' => $venda->cliente->nome ?? 'Consumidor final',
                'data' => $venda->data_venda->format('d/m/Y'),
                'forma_pagamento' => $venda->forma_pagamento ?: '—',
                'total' => round((float) $venda->total, 2),
                'situacao' => $venda->situacao,
            ])->values(),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $query = $this->repository->ordenar(
            $this->repository->consulta($this->empresaId(), $filtros['periodo'], $filtros)->with('cliente:id,nome'),
            $filtros
        );
        $situacoes = $this->situacoes();

        return CsvRelatorio::stream(
            'relatorio-vendas-'.date('Y-m-d').'.csv',
            ['Nº', 'Data', 'Cliente', 'Forma de Pagamento', 'Total', 'Situação'],
            $query->lazy(500)->map(fn ($venda) => [
                $venda->numero,
                $venda->data_venda->format('d/m/Y'),
                $venda->cliente->nome ?? 'Consumidor Final',
                $venda->forma_pagamento ?: '—',
                number_format((float) $venda->total, 2, ',', '.'),
                $situacoes[$venda->situacao] ?? $venda->situacao,
            ])
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $periodo = $filtros['periodo'];
        $totais = $this->repository->totais($this->repository->consulta($empresaId, $periodo, $filtros));
        $vendas = $this->repository->ordenar(
            $this->repository->consulta($empresaId, $periodo, $filtros)->with('cliente:id,nome'),
            $filtros
        )->limit(PdfRelatorio::LIMITE)->get();
        $situacoes = $this->situacoes();
        $aviso = PdfRelatorio::aviso($totais['registros']);

        $resumo = ['Situação: '.($situacoes[$filtros['situacao']] ?? 'Todas')];
        if ($filtros['busca'] !== '') {
            $resumo[] = 'Busca: '.$filtros['busca'];
        }
        if ($filtros['forma_pagamento'] !== '') {
            $resumo[] = 'Pagamento: '.$filtros['forma_pagamento'];
        }
        if ($aviso) {
            $resumo[] = $aviso;
        }

        $formas = $this->repository->formas($this->repository->consulta($empresaId, $periodo, $filtros), $filtros);
        $somaFormas = array_sum(array_column($formas, 'total'));
        $qtdFormas = array_sum(array_column($formas, 'vendas'));
        $participacao = array_map(function (array $forma) use ($somaFormas) {
            $percentual = $somaFormas > 0 ? (int) round($forma['total'] / $somaFormas * 100) : 0;
            $ticket = $forma['vendas'] > 0 ? $forma['total'] / $forma['vendas'] : 0;

            return [
                'nome' => $forma['nome'],
                'vendas' => $forma['vendas'],
                'ticket' => number_format($ticket, 2, ',', '.'),
                'percentual' => $percentual,
                'valor' => number_format($forma['total'], 2, ',', '.'),
            ];
        }, $formas);

        return PdfRelatorio::stream('relatorio-vendas-'.date('Y-m-d').'.pdf', [
            'titulo' => 'Vendas',
            'periodo' => $periodo->rotulo(),
            'resumo' => implode(' · ', $resumo),
            'secao' => 'Vendas',
            'kpis' => [
                ['label' => 'Vendas', 'valor' => (string) $totais['qtd_confirmadas'], 'detalhe' => 'no período'],
                ['label' => 'Faturamento', 'valor' => $this->moeda($totais['valor_confirmadas']), 'detalhe' => 'total das vendas'],
                ['label' => 'Ticket médio', 'valor' => $this->moeda($totais['ticket_medio']), 'detalhe' => 'por venda'],
            ],
            'participacao' => [
                'titulo' => 'Participação por forma',
                'linhas' => $participacao,
                'total' => [
                    'vendas' => $qtdFormas,
                    'ticket' => $qtdFormas > 0 ? number_format($somaFormas / $qtdFormas, 2, ',', '.') : '0,00',
                    'valor' => number_format($somaFormas, 2, ',', '.'),
                ],
            ],
            'colunas' => [
                ['label' => 'Número', 'width' => '12%'],
                ['label' => 'Data', 'width' => '12%'],
                ['label' => 'Cliente', 'width' => '28%'],
                ['label' => 'Pagamento', 'width' => '18%'],
                ['label' => 'Total', 'align' => 'right', 'width' => '15%'],
                ['label' => 'Situação', 'width' => '15%'],
            ],
            'linhas' => $vendas->map(fn ($venda) => [
                '#'.$venda->numero,
                $venda->data_venda->format('d/m/Y'),
                $venda->cliente->nome ?? '— Consumidor Final —',
                $venda->forma_pagamento ?: '—',
                'R$ '.number_format((float) $venda->total, 2, ',', '.'),
                $situacoes[$venda->situacao] ?? $venda->situacao,
            ])->all(),
            'totais' => [
                ['label' => 'Vendas confirmadas', 'valor' => (string) $totais['qtd_confirmadas']],
                ['label' => 'Ticket médio', 'valor' => $this->moeda($totais['ticket_medio'])],
                ['label' => 'Em andamento ('.$totais['qtd_em_andamento'].')', 'valor' => $this->moeda($totais['valor_em_andamento'])],
                ['label' => 'Canceladas ('.$totais['qtd_canceladas'].')', 'valor' => $this->moeda($totais['valor_canceladas'])],
                ['label' => 'Total das confirmadas', 'valor' => $this->moeda($totais['valor_confirmadas']), 'destaque' => true],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $periodo = Periodo::extrair($request);
        $dados = $request->validate([
            'situacao' => ['nullable', 'in:em_andamento,confirmada,cancelada'],
            'forma_pagamento' => ['nullable', 'string', 'max:100'],
            'busca' => ['nullable', 'string', 'max:120'],
            'cliente_id' => ['nullable', 'integer'],
            'funcionario_id' => ['nullable', 'integer'],
            'ordem' => ['nullable', 'in:data,numero,total,situacao'],
            'direcao' => ['nullable', 'in:asc,desc'],
        ]);

        $empresaId = $this->empresaId();

        return [
            'periodo' => $periodo,
            'data_inicio' => $periodo->inicio,
            'data_fim' => $periodo->fim,
            'situacao' => $dados['situacao'] ?? '',
            'forma_pagamento' => $dados['forma_pagamento'] ?? '',
            'busca' => $dados['busca'] ?? '',
            'cliente_id' => $this->pertence(Cliente::class, $empresaId, isset($dados['cliente_id']) ? (int) $dados['cliente_id'] : null),
            'funcionario_id' => $this->pertence(Funcionario::class, $empresaId, isset($dados['funcionario_id']) ? (int) $dados['funcionario_id'] : null),
            'ordem' => $dados['ordem'] ?? '',
            'direcao' => $dados['direcao'] ?? '',
        ];
    }

    /**
     * @param  array<string, int|float>  $totais
     * @param  array<string, int|float>  $anterior
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais, array $anterior): array
    {
        return [
            [
                'label' => 'Faturamento',
                'valor' => $this->moeda($totais['valor_confirmadas']),
                'detalhe' => $this->detalhe(
                    $totais['qtd_confirmadas'].($totais['qtd_confirmadas'] === 1 ? ' venda confirmada' : ' vendas confirmadas'),
                    $this->variacao($totais['valor_confirmadas'], $anterior['valor_confirmadas'])
                ),
                'tom' => 'success',
                'icone' => 'trending-up',
                'destaque' => true,
            ],
            [
                'label' => 'Ticket médio',
                'valor' => $this->moeda($totais['ticket_medio']),
                'detalhe' => 'Por venda confirmada',
                'tom' => 'primary',
                'icone' => 'bar-chart',
                'destaque' => false,
            ],
            [
                'label' => 'Em andamento',
                'valor' => $this->moeda($totais['valor_em_andamento']),
                'detalhe' => $totais['qtd_em_andamento'].($totais['qtd_em_andamento'] === 1 ? ' venda' : ' vendas'),
                'tom' => 'warning',
                'icone' => 'history',
                'destaque' => false,
            ],
            [
                'label' => 'Canceladas',
                'valor' => $this->moeda($totais['valor_canceladas']),
                'detalhe' => $totais['qtd_canceladas'].($totais['qtd_canceladas'] === 1 ? ' venda' : ' vendas'),
                'tom' => 'danger',
                'icone' => 'x-circle',
                'destaque' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function campos(int $empresaId, array $filtros): array
    {
        $formas = FormaPagamento::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'nome')
            ->all();

        if ($filtros['forma_pagamento'] !== '' && ! array_key_exists($filtros['forma_pagamento'], $formas)) {
            $formas = [$filtros['forma_pagamento'] => $filtros['forma_pagamento']] + $formas;
        }

        return [
            [
                'tipo' => 'select',
                'nome' => 'situacao',
                'rotulo' => 'Situação',
                'valor' => $filtros['situacao'],
                'opcoes' => $this->situacoes(),
                'vazio' => 'Todas',
            ],
            [
                'tipo' => 'select',
                'nome' => 'forma_pagamento',
                'rotulo' => 'Forma de pagamento',
                'valor' => $filtros['forma_pagamento'],
                'opcoes' => $formas,
                'vazio' => 'Todas',
            ],
            [
                'tipo' => 'busca',
                'nome' => 'cliente_id',
                'rotulo' => 'Cliente',
                'valor' => $filtros['cliente_id'],
                'texto' => $filtros['cliente_id'] ? (string) Cliente::query()->where('empresa_id', $empresaId)->whereKey($filtros['cliente_id'])->value('nome') : '',
                'url' => route('relatorio.opcoes.clientes'),
                'placeholder' => 'Nome do cliente',
            ],
            [
                'tipo' => 'busca',
                'nome' => 'funcionario_id',
                'rotulo' => 'Vendedor',
                'valor' => $filtros['funcionario_id'],
                'texto' => $filtros['funcionario_id'] ? (string) Funcionario::query()->where('empresa_id', $empresaId)->whereKey($filtros['funcionario_id'])->value('nome') : '',
                'url' => route('relatorio.opcoes.funcionarios'),
                'placeholder' => 'Nome do vendedor',
            ],
            [
                'tipo' => 'texto',
                'nome' => 'busca',
                'rotulo' => 'Busca',
                'valor' => $filtros['busca'],
                'placeholder' => 'Número ou cliente',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function situacoes(): array
    {
        return [
            'em_andamento' => 'Em andamento',
            'confirmada' => 'Confirmada',
            'cancelada' => 'Cancelada',
        ];
    }
}
