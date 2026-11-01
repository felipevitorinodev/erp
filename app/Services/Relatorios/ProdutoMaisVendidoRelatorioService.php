<?php

namespace App\Services\Relatorios;

use App\Models\Grupos\Grupo;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\PdfRelatorio;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use App\Repositories\Relatorios\ProdutoMaisVendidoRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProdutoMaisVendidoRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected ProdutoMaisVendidoRelatorioRepository $repository
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $totais = $this->repository->totais($this->repository->consulta($empresaId, $filtros['periodo'], $filtros));
        $anterior = $this->repository->totais($this->repository->consulta($empresaId, $filtros['periodo']->anterior(), $filtros));
        $produtos = $this->repository->consulta($empresaId, $filtros['periodo'], $filtros)
            ->paginate(20, ['*'], 'page', null, $totais['produtos'])
            ->withQueryString();

        return view('relatorios.consulta', array_merge(
            $this->shell('produtos-mais-vendidos', $filtros, true, ['grupo_id', 'busca']),
            [
                'parcial' => 'relatorios.parcial.produtos',
                'totais' => $totais,
                'produtos' => $produtos,
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
        $ranking = $this->repository->consulta($empresaId, $periodo, $filtros)->limit(10)->get();
        $metrica = $filtros['ordenar'] === 'valor' ? 'valor_total' : 'qtd_total';
        $formato = $metrica === 'valor_total' ? 'moeda' : 'numero';
        $fatias = $ranking->map(fn ($produto) => [
            'nome' => (string) $produto->produto_nome,
            'total' => round((float) $produto->{$metrica}, $formato === 'moeda' ? 2 : 3),
        ])->all();
        $serie = SerieTemporal::preencher($periodo, $this->serieValor($empresaId, $periodo, $filtros));

        return [
            'graficos' => [
                $this->graficoLinha('evolucao', 'Valor das confirmadas', $serie, 'Valor'),
                $this->graficoBarras('ranking', 'Top 10', $fatias, $formato),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $totais = $this->repository->totais($this->repository->consulta($this->empresaId(), $filtros['periodo'], $filtros));
        $produtos = $this->repository->consulta($this->empresaId(), $filtros['periodo'], $filtros)
            ->paginate(20, ['*'], 'page', null, $totais['produtos']);

        return [
            'total' => $produtos->total(),
            'totais' => $totais,
            'linhas' => $produtos->getCollection()->map(fn ($produto) => [
                'produto' => $produto->produto_nome,
                'codigo' => $produto->produto_codigo,
                'quantidade' => round((float) $produto->qtd_total, 3),
                'valor' => round((float) $produto->valor_total, 2),
            ])->values(),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $query = $this->repository->consulta($this->empresaId(), $filtros['periodo'], $filtros);

        return CsvRelatorio::stream(
            'relatorio-produtos-mais-vendidos-'.date('Y-m-d').'.csv',
            ['Código', 'Produto', 'Qtd', 'Valor Total'],
            $query->lazy(1000)->map(fn ($produto) => [
                $produto->produto_codigo,
                $produto->produto_nome,
                number_format((float) $produto->qtd_total, 2, ',', '.'),
                number_format((float) $produto->valor_total, 2, ',', '.'),
            ])
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $periodo = $filtros['periodo'];
        $query = $this->repository->consulta($empresaId, $periodo, $filtros);
        $totais = $this->repository->totais(clone $query);
        $produtos = (clone $query)->limit(PdfRelatorio::LIMITE)->get();
        $aviso = PdfRelatorio::aviso($totais['produtos']);

        $resumo = ['Somente vendas confirmadas'];
        if ($filtros['grupo_id']) {
            $resumo[] = 'Grupo: '.(Grupo::query()->where('empresa_id', $empresaId)->whereKey($filtros['grupo_id'])->value('nome') ?: '—');
        }
        $resumo[] = 'Ordenação: '.($filtros['ordenar'] === 'valor' ? 'Valor' : 'Quantidade');
        if ($filtros['busca'] !== '') {
            $resumo[] = 'Busca: '.$filtros['busca'];
        }
        if ($aviso) {
            $resumo[] = $aviso;
        }

        return PdfRelatorio::stream('relatorio-produtos-mais-vendidos-'.date('Y-m-d').'.pdf', [
            'titulo' => 'Produtos mais vendidos',
            'periodo' => $periodo->rotulo(),
            'resumo' => implode(' · ', $resumo),
            'secao' => 'Produtos',
            'colunas' => [
                ['label' => 'Posição', 'width' => '10%'],
                ['label' => 'Produto', 'width' => '40%'],
                ['label' => 'Código', 'width' => '15%'],
                ['label' => 'Qtd. vendida', 'align' => 'right', 'width' => '15%'],
                ['label' => 'Valor total', 'align' => 'right', 'width' => '20%'],
            ],
            'linhas' => $produtos->values()->map(fn ($produto, $indice) => [
                ($indice + 1).'º',
                $produto->produto_nome,
                $produto->produto_codigo ?: '—',
                number_format((float) $produto->qtd_total, 2, ',', '.'),
                'R$ '.number_format((float) $produto->valor_total, 2, ',', '.'),
            ])->all(),
            'totais' => [
                ['label' => 'Produtos distintos', 'valor' => (string) $totais['produtos']],
                ['label' => 'Quantidade vendida', 'valor' => number_format($totais['qtd_total'], 2, ',', '.')],
                ['label' => 'Valor total', 'valor' => $this->moeda($totais['valor_total']), 'destaque' => true],
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
            'grupo_id' => ['nullable', 'integer'],
            'ordenar' => ['nullable', 'in:qtd,valor'],
            'busca' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            'periodo' => $periodo,
            'data_inicio' => $periodo->inicio,
            'data_fim' => $periodo->fim,
            'grupo_id' => $this->pertence(Grupo::class, $this->empresaId(), isset($dados['grupo_id']) ? (int) $dados['grupo_id'] : null),
            'ordenar' => ($dados['ordenar'] ?? '') === 'valor' ? 'valor' : 'qtd',
            'busca' => $dados['busca'] ?? '',
        ];
    }

    /**
     * @param  array{produtos: int, qtd_total: float, valor_total: float}  $totais
     * @param  array{produtos: int, qtd_total: float, valor_total: float}  $anterior
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais, array $anterior): array
    {
        return [
            [
                'label' => 'Valor vendido',
                'valor' => $this->moeda($totais['valor_total']),
                'detalhe' => $this->detalhe(
                    'Somente vendas confirmadas',
                    $this->variacao($totais['valor_total'], $anterior['valor_total'])
                ),
                'tom' => 'success',
                'icone' => 'trending-up',
                'destaque' => true,
            ],
            [
                'label' => 'Quantidade vendida',
                'valor' => number_format($totais['qtd_total'], 2, ',', '.'),
                'detalhe' => 'Unidades somadas',
                'tom' => 'primary',
                'icone' => 'package',
                'destaque' => false,
            ],
            [
                'label' => 'Produtos distintos',
                'valor' => number_format($totais['produtos'], 0, ',', '.'),
                'detalhe' => 'Com venda no período',
                'tom' => 'accent',
                'icone' => 'layers',
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
        $grupos = Grupo::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();

        return [
            [
                'tipo' => 'select',
                'nome' => 'grupo_id',
                'rotulo' => 'Grupo',
                'valor' => $filtros['grupo_id'] ?? '',
                'opcoes' => $grupos,
                'vazio' => 'Todos',
            ],
            [
                'tipo' => 'select',
                'nome' => 'ordenar',
                'rotulo' => 'Ordenar por',
                'valor' => $filtros['ordenar'],
                'opcoes' => ['qtd' => 'Quantidade', 'valor' => 'Valor'],
                'vazio' => null,
            ],
            [
                'tipo' => 'texto',
                'nome' => 'busca',
                'rotulo' => 'Busca',
                'valor' => $filtros['busca'],
                'placeholder' => 'Nome ou código',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, float>
     */
    private function serieValor(int $empresaId, Periodo $periodo, array $filtros): array
    {
        $query = DB::table('venda_itens')
            ->join('vendas', 'vendas.id', '=', 'venda_itens.venda_id')
            ->where('vendas.empresa_id', $empresaId)
            ->where('vendas.situacao', 'confirmada')
            ->whereNull('vendas.deleted_at')
            ->whereDataEntre('vendas.data_venda', $periodo->inicio, $periodo->fim);

        if (! empty($filtros['grupo_id'])) {
            $query->join('produtos', 'produtos.id', '=', 'venda_itens.produto_id')
                ->where('produtos.grupo_id', $filtros['grupo_id']);
        }

        if (($filtros['busca'] ?? '') !== '') {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca) {
                $q->where('venda_itens.produto_nome', 'like', "%{$busca}%")
                    ->orWhere('venda_itens.produto_codigo', 'like', "%{$busca}%");
            });
        }

        if ($periodo->porMes()) {
            $chave = SerieTemporal::chaveMes('vendas.data_venda');
            $linhas = $query
                ->selectRaw($chave.' as chave')
                ->selectRaw('COALESCE(SUM(venda_itens.total), 0) as total')
                ->groupByRaw($chave)
                ->get();
            $tamanho = 7;
        } else {
            $linhas = $query
                ->selectRaw('vendas.data_venda as chave')
                ->selectRaw('COALESCE(SUM(venda_itens.total), 0) as total')
                ->groupBy('vendas.data_venda')
                ->get();
            $tamanho = 10;
        }

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[substr((string) $linha->chave, 0, $tamanho)] = (float) $linha->total;
        }

        return $mapa;
    }
}
