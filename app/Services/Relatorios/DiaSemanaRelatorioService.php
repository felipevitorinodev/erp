<?php

namespace App\Services\Relatorios;

use App\Relatorios\CsvRelatorio;
use App\Relatorios\DiaSemana;
use App\Relatorios\PdfRelatorio;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use App\Repositories\Relatorios\DiaSemanaRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiaSemanaRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected DiaSemanaRelatorioRepository $repository
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $linhas = $this->linhas($empresaId, $filtros['periodo']);
        $totais = $this->totais($linhas);
        $anterior = $this->totais($this->linhas($empresaId, $filtros['periodo']->anterior()));

        return view('relatorios.consulta', array_merge(
            $this->shell('vendas-por-dia-semana', $filtros, true, []),
            [
                'parcial' => 'relatorios.parcial.dia-semana',
                'totais' => $totais,
                'dias' => $this->paginarLista($linhas)->withQueryString(),
                'kpis' => $this->kpis($totais, $anterior),
                'campos' => [],
            ]
        ));
    }

    /**
     * @return array{graficos: list<array<string, mixed>>}
     */
    public function resumo(Request $request): array
    {
        $filtros = $this->validar($request);
        $periodo = $filtros['periodo'];
        $linhas = $this->linhas($this->empresaId(), $periodo);
        $consulta = $this->repository->consulta($this->empresaId(), $periodo);
        $serie = SerieTemporal::preencher($periodo, SerieTemporal::mapa($consulta, $periodo, 'data_venda', 'total'));
        $fatias = array_map(fn (array $linha) => [
            'nome' => $linha['curto'],
            'total' => $linha['valor'],
        ], $linhas);

        return [
            'graficos' => [
                $this->graficoBarras('semana', 'Faturamento por dia da semana', $fatias, 'moeda'),
                $this->graficoLinha('evolucao', 'Faturamento das confirmadas', $serie, 'Valor'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $linhas = $this->linhas($this->empresaId(), $filtros['periodo']);

        return [
            'total' => count($linhas),
            'totais' => $this->totais($linhas),
            'linhas' => $linhas,
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $linhas = $this->linhas($this->empresaId(), $filtros['periodo']);

        return CsvRelatorio::stream(
            'relatorio-vendas-por-dia-semana-'.date('Y-m-d').'.csv',
            ['Dia', 'Vendas', 'Faturamento', 'Ticket médio', 'Participação'],
            array_map(fn (array $linha) => [
                $linha['nome'],
                $linha['qtd'],
                number_format($linha['valor'], 2, ',', '.'),
                number_format($linha['ticket'], 2, ',', '.'),
                number_format($linha['percentual'], 1, ',', '.').'%',
            ], $linhas)
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $linhas = $this->linhas($this->empresaId(), $filtros['periodo']);
        $totais = $this->totais($linhas);

        return PdfRelatorio::stream('relatorio-vendas-por-dia-semana-'.date('Y-m-d').'.pdf', [
            'titulo' => 'Vendas por dia da semana',
            'periodo' => $filtros['periodo']->rotulo(),
            'resumo' => 'Somente vendas confirmadas',
            'secao' => 'Dias da semana',
            'colunas' => [
                ['label' => 'Dia', 'width' => '24%'],
                ['label' => 'Vendas', 'align' => 'right', 'width' => '16%'],
                ['label' => 'Faturamento', 'align' => 'right', 'width' => '22%'],
                ['label' => 'Ticket', 'align' => 'right', 'width' => '20%'],
                ['label' => 'Participação', 'align' => 'right', 'width' => '18%'],
            ],
            'linhas' => array_map(fn (array $linha) => [
                $linha['nome'],
                (string) $linha['qtd'],
                $this->moeda($linha['valor']),
                $this->moeda($linha['ticket']),
                number_format($linha['percentual'], 1, ',', '.').'%',
            ], $linhas),
            'totais' => [
                ['label' => 'Vendas confirmadas', 'valor' => (string) $totais['qtd']],
                ['label' => 'Ticket médio', 'valor' => $this->moeda($totais['ticket'])],
                ['label' => 'Dia de pico', 'valor' => $totais['pico']],
                ['label' => 'Total das confirmadas', 'valor' => $this->moeda($totais['faturamento']), 'destaque' => true],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $periodo = Periodo::extrair($request);

        return [
            'periodo' => $periodo,
            'data_inicio' => $periodo->inicio,
            'data_fim' => $periodo->fim,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linhas(int $empresaId, Periodo $periodo): array
    {
        $mapa = $this->repository->porDia($this->repository->consulta($empresaId, $periodo));
        $total = round((float) array_sum(array_column($mapa, 'valor')), 2);
        $linhas = [];

        foreach (DiaSemana::ORDEM as $dia) {
            $qtd = $mapa[$dia]['qtd'] ?? 0;
            $valor = $mapa[$dia]['valor'] ?? 0.0;
            $linhas[] = [
                'dia' => $dia,
                'nome' => DiaSemana::nome($dia),
                'curto' => DiaSemana::curto($dia),
                'qtd' => $qtd,
                'valor' => $valor,
                'ticket' => $qtd > 0 ? round($valor / $qtd, 2) : 0.0,
                'percentual' => $total > 0 ? round($valor / $total * 100, 1) : 0.0,
            ];
        }

        return $linhas;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, int|float|string>
     */
    private function totais(array $linhas): array
    {
        $faturamento = round((float) array_sum(array_column($linhas, 'valor')), 2);
        $qtd = (int) array_sum(array_column($linhas, 'qtd'));
        $pico = '—';
        $maior = -1.0;

        foreach ($linhas as $linha) {
            if ($linha['valor'] > $maior) {
                $maior = $linha['valor'];
                $pico = $linha['valor'] > 0 ? $linha['nome'] : '—';
            }
        }

        return [
            'faturamento' => $faturamento,
            'qtd' => $qtd,
            'ticket' => $qtd > 0 ? round($faturamento / $qtd, 2) : 0.0,
            'pico' => $pico,
            'pico_valor' => $maior > 0 ? $maior : 0.0,
        ];
    }

    /**
     * @param  array<string, int|float|string>  $totais
     * @param  array<string, int|float|string>  $anterior
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais, array $anterior): array
    {
        return [
            [
                'label' => 'Faturamento',
                'valor' => $this->moeda((float) $totais['faturamento']),
                'detalhe' => $this->detalhe(
                    $totais['qtd'].($totais['qtd'] === 1 ? ' venda confirmada' : ' vendas confirmadas'),
                    $this->variacao((float) $totais['faturamento'], (float) $anterior['faturamento'])
                ),
                'tom' => 'success',
                'icone' => 'trending-up',
                'destaque' => true,
            ],
            [
                'label' => 'Ticket médio',
                'valor' => $this->moeda((float) $totais['ticket']),
                'detalhe' => 'Por venda confirmada',
                'tom' => 'primary',
                'icone' => 'bar-chart',
                'destaque' => false,
            ],
            [
                'label' => 'Dia de pico',
                'valor' => (string) $totais['pico'],
                'detalhe' => $totais['pico'] === '—' ? 'Sem vendas confirmadas' : $this->moeda((float) $totais['pico_valor']),
                'tom' => 'warning',
                'icone' => 'history',
                'destaque' => false,
            ],
            [
                'label' => 'Vendas',
                'valor' => (string) $totais['qtd'],
                'detalhe' => 'Confirmadas no período',
                'tom' => 'primary',
                'icone' => 'check',
                'destaque' => false,
            ],
        ];
    }
}
