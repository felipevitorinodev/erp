<?php

namespace App\Services\Relatorios;

use App\Models\Clientes\Cliente;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\CurvaAbc;
use App\Relatorios\PdfRelatorio;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use App\Repositories\Relatorios\RankingClienteRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RankingClienteRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected RankingClienteRelatorioRepository $repository
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $classificado = $this->classificado($empresaId, $filtros['periodo']);
        $linhas = $this->filtrar($classificado, $filtros);
        $totais = $this->totais($classificado, $linhas);
        $anterior = $this->faturamento($empresaId, $filtros['periodo']->anterior());

        return view('relatorios.consulta', array_merge(
            $this->shell('ranking-clientes', $filtros, true, ['classe', 'cliente_id', 'busca']),
            [
                'parcial' => 'relatorios.parcial.ranking-clientes',
                'totais' => $totais,
                'ranking' => $this->paginarLista($linhas)->withQueryString(),
                'kpis' => $this->kpis($totais, $anterior, $filtros),
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
        $linhas = $this->filtrar($this->classificado($empresaId, $periodo), $filtros);
        $consulta = $this->repository->consulta($empresaId, $periodo);
        $serie = SerieTemporal::preencher($periodo, SerieTemporal::mapa($consulta, $periodo, 'data_venda', 'total'));
        $fatias = [];
        foreach (array_slice($linhas, 0, 8) as $linha) {
            $fatias[] = ['nome' => $linha['nome'], 'total' => $linha['valor']];
        }

        return [
            'graficos' => [
                $this->graficoLinha('evolucao', 'Faturamento das confirmadas', $serie, 'Valor'),
                $this->graficoBarras('clientes', 'Maiores clientes', $fatias, 'moeda'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $classificado = $this->classificado($this->empresaId(), $filtros['periodo']);
        $linhas = $this->filtrar($classificado, $filtros);
        $pagina = $this->paginarLista($linhas);

        return [
            'total' => $pagina->total(),
            'totais' => $this->totais($classificado, $linhas),
            'linhas' => array_values($pagina->items()),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $linhas = $this->filtrar($this->classificado($this->empresaId(), $filtros['periodo']), $filtros);

        return CsvRelatorio::stream(
            'relatorio-ranking-clientes-'.date('Y-m-d').'.csv',
            ['Cliente', 'Classe', 'Vendas', 'Faturamento', 'Participação', 'Acumulado', 'Ticket médio'],
            array_map(fn (array $linha) => [
                $linha['nome'],
                $linha['classe'],
                $linha['qtd'],
                number_format($linha['valor'], 2, ',', '.'),
                number_format($linha['percentual'], 1, ',', '.').'%',
                number_format($linha['acumulado'], 1, ',', '.').'%',
                number_format($linha['ticket'], 2, ',', '.'),
            ], $linhas)
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $classificado = $this->classificado($this->empresaId(), $filtros['periodo']);
        $linhas = $this->filtrar($classificado, $filtros);
        $totais = $this->totais($classificado, $linhas);
        $aviso = PdfRelatorio::aviso(count($linhas));
        $resumo = ['Corte A 80% · B 15% · C 5%', 'Ranking por faturamento'];
        if ($filtros['classe'] !== '') {
            $resumo[] = 'Classe '.$filtros['classe'];
        }
        if ($aviso) {
            $resumo[] = $aviso;
        }

        return PdfRelatorio::stream('relatorio-ranking-clientes-'.date('Y-m-d').'.pdf', [
            'titulo' => 'Ranking de clientes',
            'periodo' => $filtros['periodo']->rotulo(),
            'resumo' => implode(' · ', $resumo),
            'secao' => 'Clientes',
            'colunas' => [
                ['label' => 'Cliente', 'width' => '28%'],
                ['label' => 'Classe', 'width' => '8%'],
                ['label' => 'Vendas', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Faturamento', 'align' => 'right', 'width' => '16%'],
                ['label' => 'Part.', 'align' => 'right', 'width' => '12%'],
                ['label' => 'Acum.', 'align' => 'right', 'width' => '12%'],
                ['label' => 'Ticket', 'align' => 'right', 'width' => '14%'],
            ],
            'linhas' => array_map(fn (array $linha) => [
                $linha['nome'],
                $linha['classe'],
                (string) $linha['qtd'],
                $this->moeda($linha['valor']),
                number_format($linha['percentual'], 1, ',', '.').'%',
                number_format($linha['acumulado'], 1, ',', '.').'%',
                $this->moeda($linha['ticket']),
            ], array_slice($linhas, 0, PdfRelatorio::LIMITE)),
            'totais' => [
                ['label' => 'Clientes', 'valor' => (string) $totais['qtd_clientes']],
                ['label' => 'Classe A', 'valor' => (string) $totais['classe_a']],
                ['label' => 'Total da seleção', 'valor' => $this->moeda($totais['faturamento_lista'])],
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
            'classe' => ['nullable', 'in:A,B,C'],
            'cliente_id' => ['nullable', 'integer'],
            'busca' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            'periodo' => $periodo,
            'data_inicio' => $periodo->inicio,
            'data_fim' => $periodo->fim,
            'classe' => $dados['classe'] ?? '',
            'cliente_id' => $this->pertence(Cliente::class, $this->empresaId(), isset($dados['cliente_id']) ? (int) $dados['cliente_id'] : null),
            'busca' => $dados['busca'] ?? '',
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function classificado(int $empresaId, Periodo $periodo): array
    {
        $brutas = $this->repository->linhas($this->repository->consulta($empresaId, $periodo));
        $ids = array_values(array_filter(array_column($brutas, 'cliente_id')));
        $nomes = $ids === []
            ? collect()
            : Cliente::withTrashed()->where('empresa_id', $empresaId)->whereIn('id', $ids)->pluck('nome', 'id');

        $linhas = [];
        foreach ($brutas as $linha) {
            $linhas[] = [
                'cliente_id' => $linha['cliente_id'],
                'nome' => $linha['cliente_id']
                    ? (string) ($nomes[$linha['cliente_id']] ?? 'Cliente #'.$linha['cliente_id'])
                    : 'Consumidor final',
                'qtd' => $linha['qtd'],
                'valor' => $linha['valor'],
                'ticket' => $linha['qtd'] > 0 ? round($linha['valor'] / $linha['qtd'], 2) : 0.0,
            ];
        }

        return CurvaAbc::classificar($linhas);
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function filtrar(array $linhas, array $filtros): array
    {
        if ($filtros['classe'] !== '') {
            $linhas = array_values(array_filter($linhas, fn (array $linha) => $linha['classe'] === $filtros['classe']));
        }

        if (! empty($filtros['cliente_id'])) {
            $linhas = array_values(array_filter(
                $linhas,
                fn (array $linha) => $linha['cliente_id'] === $filtros['cliente_id']
            ));
        }

        if ($filtros['busca'] !== '') {
            $termo = mb_strtolower($filtros['busca']);
            $linhas = array_values(array_filter(
                $linhas,
                fn (array $linha) => str_contains(mb_strtolower((string) $linha['nome']), $termo)
            ));
        }

        return $linhas;
    }

    private function faturamento(int $empresaId, Periodo $periodo): float
    {
        return round((float) array_sum(array_column(
            $this->repository->linhas($this->repository->consulta($empresaId, $periodo)),
            'valor'
        )), 2);
    }

    /**
     * @param  list<array<string, mixed>>  $classificado
     * @param  list<array<string, mixed>>  $lista
     * @return array<string, int|float|string>
     */
    private function totais(array $classificado, array $lista): array
    {
        $contagem = ['A' => 0, 'B' => 0, 'C' => 0];
        foreach ($classificado as $linha) {
            $contagem[$linha['classe']] = ($contagem[$linha['classe']] ?? 0) + 1;
        }

        return [
            'faturamento' => round((float) array_sum(array_column($classificado, 'valor')), 2),
            'qtd_vendas' => (int) array_sum(array_column($classificado, 'qtd')),
            'qtd_clientes' => count($classificado),
            'faturamento_lista' => round((float) array_sum(array_column($lista, 'valor')), 2),
            'qtd_lista' => count($lista),
            'classe_a' => $contagem['A'],
            'classe_b' => $contagem['B'],
            'classe_c' => $contagem['C'],
            'lider' => $classificado[0]['nome'] ?? '—',
        ];
    }

    /**
     * @param  array<string, int|float|string>  $totais
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais, float $anterior, array $filtros): array
    {
        $detalhe = $totais['qtd_vendas'].($totais['qtd_vendas'] === 1 ? ' venda confirmada' : ' vendas confirmadas');
        if ($filtros['classe'] !== '') {
            $detalhe .= ' · classe '.$filtros['classe'].': '.$this->moeda((float) $totais['faturamento_lista']);
        }

        return [
            [
                'label' => 'Faturamento',
                'valor' => $this->moeda((float) $totais['faturamento']),
                'detalhe' => $this->detalhe($detalhe, $this->variacao((float) $totais['faturamento'], $anterior)),
                'tom' => 'success',
                'icone' => 'trending-up',
                'destaque' => true,
            ],
            [
                'label' => 'Clientes',
                'valor' => (string) $totais['qtd_clientes'],
                'detalhe' => 'Na curva do período',
                'tom' => 'primary',
                'icone' => 'bar-chart',
                'destaque' => false,
            ],
            [
                'label' => 'Classe A',
                'valor' => (string) $totais['classe_a'],
                'detalhe' => 'Até 80% do faturamento',
                'tom' => 'warning',
                'icone' => 'layers',
                'destaque' => false,
            ],
            [
                'label' => 'Maior cliente',
                'valor' => (string) $totais['lider'],
                'detalhe' => $totais['qtd_clientes'] > 0 ? 'Primeiro do ranking' : 'Sem vendas confirmadas',
                'tom' => 'primary',
                'icone' => 'check',
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
                'nome' => 'classe',
                'rotulo' => 'Classe',
                'valor' => $filtros['classe'],
                'opcoes' => ['A' => 'A', 'B' => 'B', 'C' => 'C'],
                'vazio' => 'Todas',
            ],
            [
                'tipo' => 'busca',
                'nome' => 'cliente_id',
                'rotulo' => 'Cliente',
                'valor' => $filtros['cliente_id'],
                'texto' => $filtros['cliente_id']
                    ? (string) Cliente::query()->where('empresa_id', $empresaId)->whereKey($filtros['cliente_id'])->value('nome')
                    : '',
                'url' => route('relatorio.opcoes.clientes'),
                'placeholder' => 'Nome do cliente',
            ],
            [
                'tipo' => 'texto',
                'nome' => 'busca',
                'rotulo' => 'Busca',
                'valor' => $filtros['busca'],
                'placeholder' => 'Nome do cliente',
            ],
        ];
    }
}
