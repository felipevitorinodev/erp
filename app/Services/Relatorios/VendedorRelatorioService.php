<?php

namespace App\Services\Relatorios;

use App\Models\Funcionarios\Funcionario;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\PdfRelatorio;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use App\Repositories\Relatorios\VendedorRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VendedorRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected VendedorRelatorioRepository $repository
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $linhas = $this->linhas($empresaId, $filtros['periodo'], $filtros);
        $totais = $this->totais($linhas);
        $anterior = $this->totais($this->linhas($empresaId, $filtros['periodo']->anterior(), $filtros));

        return view('relatorios.consulta', array_merge(
            $this->shell('vendas-por-vendedor', $filtros, true, ['situacao', 'funcionario_id']),
            [
                'parcial' => 'relatorios.parcial.vendedores',
                'totais' => $totais,
                'vendedores' => $this->paginarLista($linhas)->withQueryString(),
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
        $linhas = $this->linhas($empresaId, $periodo, $filtros);
        $consulta = $this->repository->consulta($empresaId, $periodo, $filtros);
        if ($filtros['situacao'] === '') {
            $consulta->where('situacao', 'confirmada');
        }

        $serie = SerieTemporal::preencher($periodo, SerieTemporal::mapa($consulta, $periodo, 'data_venda', 'total'));
        $fatias = [];
        foreach (array_slice($linhas, 0, 8) as $linha) {
            if ($linha['faturamento'] == 0.0) {
                continue;
            }
            $fatias[] = ['nome' => $linha['nome'], 'total' => $linha['faturamento']];
        }

        $titulo = $filtros['situacao'] === '' || $filtros['situacao'] === 'confirmada'
            ? 'Faturamento das confirmadas'
            : 'Valor no período';

        return [
            'graficos' => [
                $this->graficoLinha('evolucao', $titulo, $serie, 'Valor'),
                $this->graficoBarras('vendedores', 'Faturamento por vendedor', $fatias, 'moeda'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $linhas = $this->linhas($this->empresaId(), $filtros['periodo'], $filtros);
        $pagina = $this->paginarLista($linhas);

        return [
            'total' => $pagina->total(),
            'totais' => $this->totais($linhas),
            'linhas' => array_values($pagina->items()),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $linhas = $this->linhas($this->empresaId(), $filtros['periodo'], $filtros);

        return CsvRelatorio::stream(
            'relatorio-vendas-por-vendedor-'.date('Y-m-d').'.csv',
            ['Vendedor', 'Confirmadas', 'Faturamento', 'Ticket médio', 'Em andamento', 'Canceladas'],
            array_map(fn (array $linha) => [
                $linha['nome'],
                $linha['qtd_confirmadas'],
                number_format($linha['faturamento'], 2, ',', '.'),
                number_format($linha['ticket'], 2, ',', '.'),
                $linha['qtd_andamento'],
                $linha['qtd_canceladas'],
            ], $linhas)
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $linhas = $this->linhas($this->empresaId(), $filtros['periodo'], $filtros);
        $totais = $this->totais($linhas);
        $aviso = PdfRelatorio::aviso(count($linhas));
        $resumo = ['Situação: '.($this->situacoes()[$filtros['situacao']] ?? 'Todas')];
        if ($aviso) {
            $resumo[] = $aviso;
        }

        return PdfRelatorio::stream('relatorio-vendas-por-vendedor-'.date('Y-m-d').'.pdf', [
            'titulo' => 'Vendas por vendedor',
            'periodo' => $filtros['periodo']->rotulo(),
            'resumo' => implode(' · ', $resumo),
            'secao' => 'Vendedores',
            'colunas' => [
                ['label' => 'Vendedor', 'width' => '28%'],
                ['label' => 'Confirmadas', 'align' => 'right', 'width' => '14%'],
                ['label' => 'Faturamento', 'align' => 'right', 'width' => '18%'],
                ['label' => 'Ticket', 'align' => 'right', 'width' => '16%'],
                ['label' => 'Em andamento', 'align' => 'right', 'width' => '12%'],
                ['label' => 'Canceladas', 'align' => 'right', 'width' => '12%'],
            ],
            'linhas' => array_map(fn (array $linha) => [
                $linha['nome'],
                (string) $linha['qtd_confirmadas'],
                $this->moeda($linha['faturamento']),
                $this->moeda($linha['ticket']),
                (string) $linha['qtd_andamento'],
                (string) $linha['qtd_canceladas'],
            ], array_slice($linhas, 0, PdfRelatorio::LIMITE)),
            'totais' => [
                ['label' => 'Vendas confirmadas', 'valor' => (string) $totais['qtd_confirmadas']],
                ['label' => 'Ticket médio', 'valor' => $this->moeda($totais['ticket'])],
                ['label' => 'Sem vendedor', 'valor' => (string) $totais['qtd_sem_vendedor']],
                ['label' => 'Total das confirmadas', 'valor' => $this->moeda($totais['faturamento']), 'destaque' => true],
            ],
        ], 'landscape');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $periodo = Periodo::extrair($request);
        $dados = $request->validate([
            'situacao' => ['nullable', 'in:em_andamento,confirmada,cancelada'],
            'funcionario_id' => ['nullable', 'integer'],
            'ordem' => ['nullable', 'in:faturamento,qtd,ticket,nome'],
            'direcao' => ['nullable', 'in:asc,desc'],
        ]);

        return [
            'periodo' => $periodo,
            'data_inicio' => $periodo->inicio,
            'data_fim' => $periodo->fim,
            'situacao' => $dados['situacao'] ?? '',
            'funcionario_id' => $this->pertence(Funcionario::class, $this->empresaId(), isset($dados['funcionario_id']) ? (int) $dados['funcionario_id'] : null),
            'ordem' => $dados['ordem'] ?? '',
            'direcao' => $dados['direcao'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function linhas(int $empresaId, Periodo $periodo, array $filtros): array
    {
        $brutas = $this->repository->linhas($this->repository->consulta($empresaId, $periodo, $filtros));
        $ids = array_values(array_filter(array_column($brutas, 'funcionario_id')));
        $nomes = $ids === []
            ? collect()
            : Funcionario::withTrashed()->where('empresa_id', $empresaId)->whereIn('id', $ids)->pluck('nome', 'id');

        $linhas = [];
        foreach ($brutas as $linha) {
            $qtd = $linha['qtd_confirmadas'];
            $linhas[] = [
                'funcionario_id' => $linha['funcionario_id'],
                'nome' => $linha['funcionario_id']
                    ? (string) ($nomes[$linha['funcionario_id']] ?? 'Vendedor #'.$linha['funcionario_id'])
                    : 'Sem vendedor',
                'qtd_confirmadas' => $qtd,
                'faturamento' => $linha['faturamento'],
                'ticket' => $qtd > 0 ? round($linha['faturamento'] / $qtd, 2) : 0.0,
                'qtd_andamento' => $linha['qtd_andamento'],
                'qtd_canceladas' => $linha['qtd_canceladas'],
            ];
        }

        $direcao = ($filtros['direcao'] ?? '') === 'asc' ? 1 : -1;
        $campo = match ($filtros['ordem'] ?? '') {
            'qtd' => 'qtd_confirmadas',
            'nome' => 'nome',
            'ticket' => 'ticket',
            default => 'faturamento',
        };

        usort($linhas, function (array $a, array $b) use ($campo, $direcao): int {
            $cmp = $campo === 'nome'
                ? strcasecmp((string) $a['nome'], (string) $b['nome'])
                : $a[$campo] <=> $b[$campo];

            if ($cmp === 0) {
                $cmp = strcasecmp((string) $a['nome'], (string) $b['nome']);
            }

            return $direcao === 1 ? $cmp : -$cmp;
        });

        return $linhas;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, int|float>
     */
    private function totais(array $linhas): array
    {
        $faturamento = round((float) array_sum(array_column($linhas, 'faturamento')), 2);
        $qtd = (int) array_sum(array_column($linhas, 'qtd_confirmadas'));
        $sem = 0;
        $semValor = 0.0;

        foreach ($linhas as $linha) {
            if ($linha['funcionario_id'] === null) {
                $sem += $linha['qtd_confirmadas'];
                $semValor += $linha['faturamento'];
            }
        }

        return [
            'faturamento' => $faturamento,
            'qtd_confirmadas' => $qtd,
            'ticket' => $qtd > 0 ? round($faturamento / $qtd, 2) : 0.0,
            'qtd_andamento' => (int) array_sum(array_column($linhas, 'qtd_andamento')),
            'qtd_canceladas' => (int) array_sum(array_column($linhas, 'qtd_canceladas')),
            'qtd_sem_vendedor' => $sem,
            'faturamento_sem_vendedor' => round($semValor, 2),
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
                'valor' => $this->moeda($totais['faturamento']),
                'detalhe' => $this->detalhe(
                    $totais['qtd_confirmadas'].($totais['qtd_confirmadas'] === 1 ? ' venda confirmada' : ' vendas confirmadas'),
                    $this->variacao((float) $totais['faturamento'], (float) $anterior['faturamento'])
                ),
                'tom' => 'success',
                'icone' => 'trending-up',
                'destaque' => true,
            ],
            [
                'label' => 'Ticket médio',
                'valor' => $this->moeda($totais['ticket']),
                'detalhe' => 'Por venda confirmada',
                'tom' => 'primary',
                'icone' => 'bar-chart',
                'destaque' => false,
            ],
            [
                'label' => 'Em andamento',
                'valor' => (string) $totais['qtd_andamento'],
                'detalhe' => $totais['qtd_canceladas'].($totais['qtd_canceladas'] === 1 ? ' cancelada' : ' canceladas'),
                'tom' => 'warning',
                'icone' => 'history',
                'destaque' => false,
            ],
            [
                'label' => 'Sem vendedor',
                'valor' => (string) $totais['qtd_sem_vendedor'],
                'detalhe' => $this->moeda($totais['faturamento_sem_vendedor']).' em confirmadas',
                'tom' => 'danger',
                'icone' => 'alert-triangle',
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
                'tipo' => 'busca',
                'nome' => 'funcionario_id',
                'rotulo' => 'Vendedor',
                'valor' => $filtros['funcionario_id'],
                'texto' => $filtros['funcionario_id']
                    ? (string) Funcionario::query()->where('empresa_id', $empresaId)->whereKey($filtros['funcionario_id'])->value('nome')
                    : '',
                'url' => route('relatorio.opcoes.funcionarios'),
                'placeholder' => 'Nome do vendedor',
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
