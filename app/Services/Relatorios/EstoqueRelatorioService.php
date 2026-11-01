<?php

namespace App\Services\Relatorios;

use App\Models\Grupos\Grupo;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\PdfRelatorio;
use App\Repositories\Relatorios\EstoqueRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstoqueRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected EstoqueRelatorioRepository $repository
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $totais = $this->repository->totais($this->repository->consulta($this->empresaId(), $filtros));
        $produtos = $this->repository->ordenar(
            $this->repository->consulta($this->empresaId(), $filtros)->with(['grupos:id,nome', 'unidadeMedida:id,nome,sigla']),
            $filtros
        )->paginate(20, ['*'], 'page', null, $totais['produtos'])->withQueryString();

        return view('relatorios.consulta', array_merge(
            $this->shell('estoque', $filtros, false, ['grupo_id', 'estoque', 'busca', 'apenas_critico']),
            [
                'parcial' => 'relatorios.parcial.estoque',
                'totais' => $totais,
                'produtos' => $produtos,
                'kpis' => $this->kpis($totais),
                'campos' => $this->campos($this->empresaId(), $filtros),
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
        $totais = $this->repository->totais($this->repository->consulta($empresaId, $filtros));

        return [
            'graficos' => [
                $this->graficoBarras('grupos', 'Valor em custo por grupo', $this->repository->porGrupo($this->repository->consulta($empresaId, $filtros)), 'moeda'),
                $this->graficoBarras('situacao', 'Situação do estoque', $this->repository->situacao($totais), 'numero'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $totais = $this->repository->totais($this->repository->consulta($this->empresaId(), $filtros));
        $produtos = $this->repository->ordenar(
            $this->repository->consulta($this->empresaId(), $filtros)->with(['grupos:id,nome', 'unidadeMedida:id,sigla']),
            $filtros
        )->paginate(20, ['*'], 'page', null, $totais['produtos']);

        return [
            'total' => $produtos->total(),
            'totais' => $totais,
            'linhas' => $produtos->getCollection()->map(fn ($produto) => [
                'codigo' => $produto->codigo,
                'nome' => $produto->nome,
                'grupo' => $produto->grupos->nome ?? null,
                'estoque_atual' => round((float) $produto->estoque_atual, 3),
                'estoque_minimo' => round((float) $produto->estoque_minimo, 3),
            ])->values(),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $query = $this->repository->ordenar(
            $this->repository->consulta($this->empresaId(), $filtros)->with(['grupos:id,nome', 'unidadeMedida:id,nome,sigla']),
            $filtros
        );

        return CsvRelatorio::stream(
            'relatorio-estoque-'.date('Y-m-d').'.csv',
            ['Código', 'Produto', 'Grupo', 'Estoque', 'Mínimo', 'Unidade'],
            $query->lazy(500)->map(fn ($produto) => [
                $produto->codigo,
                $produto->nome,
                $produto->grupos->nome ?? '—',
                number_format((float) $produto->estoque_atual, 2, ',', '.'),
                number_format((float) $produto->estoque_minimo, 2, ',', '.'),
                $produto->unidadeMedida->sigla ?? '—',
            ])
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $query = $this->repository->ordenar(
            $this->repository->consulta($empresaId, $filtros)->with(['grupos:id,nome', 'unidadeMedida:id,nome,sigla']),
            $filtros
        );
        $totais = $this->repository->totais(clone $query);
        $produtos = (clone $query)->limit(PdfRelatorio::LIMITE)->get();
        $aviso = PdfRelatorio::aviso($totais['produtos']);

        $grupoNome = 'Todos';
        if ($filtros['grupo_id']) {
            $grupoNome = Grupo::query()->where('empresa_id', $empresaId)->whereKey($filtros['grupo_id'])->value('nome') ?: '—';
        }

        $resumo = ['Grupo: '.$grupoNome];
        if ($filtros['apenas_critico']) {
            $resumo[] = 'Somente estoque crítico';
        }
        if ($filtros['estoque'] !== '') {
            $resumo[] = 'Estoque: '.($filtros['estoque'] === 'zerado' ? 'Zerado' : 'Com estoque');
        }
        if ($filtros['busca'] !== '') {
            $resumo[] = 'Busca: '.$filtros['busca'];
        }
        if ($aviso) {
            $resumo[] = $aviso;
        }

        return PdfRelatorio::stream('relatorio-estoque-'.date('Y-m-d').'.pdf', [
            'titulo' => 'Posição de estoque',
            'periodo' => now()->format('d/m/Y'),
            'resumo' => implode(' · ', $resumo),
            'secao' => 'Produtos',
            'colunas' => [
                ['label' => 'Código', 'width' => '12%'],
                ['label' => 'Produto', 'width' => '34%'],
                ['label' => 'Grupo', 'width' => '18%'],
                ['label' => 'Unidade', 'width' => '12%'],
                ['label' => 'Mínimo', 'align' => 'right', 'width' => '12%'],
                ['label' => 'Atual', 'align' => 'right', 'width' => '12%'],
            ],
            'linhas' => $produtos->map(fn ($produto) => [
                $produto->codigo ?: '—',
                $produto->nome,
                $produto->grupos->nome ?? '—',
                $produto->unidadeMedida->sigla ?? ($produto->unidadeMedida->nome ?? '—'),
                number_format((float) $produto->estoque_minimo, 2, ',', '.'),
                number_format((float) $produto->estoque_atual, 2, ',', '.'),
            ])->all(),
            'totais' => [
                ['label' => 'Produtos', 'valor' => (string) $totais['produtos']],
                ['label' => 'Estoque crítico', 'valor' => (string) $totais['criticos']],
                ['label' => 'Sem estoque', 'valor' => (string) $totais['zerados']],
                ['label' => 'Valor em estoque (custo)', 'valor' => $this->moeda($totais['valor_custo']), 'destaque' => true],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'grupo_id' => ['nullable', 'integer'],
            'estoque' => ['nullable', 'in:zerado,com_estoque'],
            'busca' => ['nullable', 'string', 'max:120'],
            'apenas_critico' => ['nullable', 'boolean'],
            'ordem' => ['nullable', 'in:codigo,nome,minimo,atual'],
            'direcao' => ['nullable', 'in:asc,desc'],
        ]);

        return [
            'grupo_id' => $this->pertence(Grupo::class, $this->empresaId(), isset($dados['grupo_id']) ? (int) $dados['grupo_id'] : null),
            'estoque' => $dados['estoque'] ?? '',
            'busca' => $dados['busca'] ?? '',
            'apenas_critico' => $request->boolean('apenas_critico'),
            'ordem' => $dados['ordem'] ?? '',
            'direcao' => $dados['direcao'] ?? '',
        ];
    }

    /**
     * @param  array{produtos: int, criticos: int, zerados: int, valor_custo: float}  $totais
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais): array
    {
        return [
            [
                'label' => 'Produtos',
                'valor' => number_format($totais['produtos'], 0, ',', '.'),
                'detalhe' => 'Com controle de estoque',
                'tom' => 'primary',
                'icone' => 'package',
                'destaque' => false,
            ],
            [
                'label' => 'Valor em estoque',
                'valor' => $this->moeda($totais['valor_custo']),
                'detalhe' => 'A preço de custo',
                'tom' => 'success',
                'icone' => 'dollar',
                'destaque' => true,
            ],
            [
                'label' => 'Estoque crítico',
                'valor' => number_format($totais['criticos'], 0, ',', '.'),
                'detalhe' => 'Atual no mínimo ou abaixo',
                'tom' => 'warning',
                'icone' => 'alert-triangle',
                'destaque' => false,
            ],
            [
                'label' => 'Sem estoque',
                'valor' => number_format($totais['zerados'], 0, ',', '.'),
                'detalhe' => 'Saldo zerado ou negativo',
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
        return [
            [
                'tipo' => 'select',
                'nome' => 'grupo_id',
                'rotulo' => 'Grupo',
                'valor' => $filtros['grupo_id'] ?? '',
                'opcoes' => Grupo::query()->where('empresa_id', $empresaId)->where('ativo', true)->orderBy('nome')->pluck('nome', 'id')->all(),
                'vazio' => 'Todos',
            ],
            [
                'tipo' => 'select',
                'nome' => 'estoque',
                'rotulo' => 'Estoque',
                'valor' => $filtros['estoque'],
                'opcoes' => ['zerado' => 'Zerado', 'com_estoque' => 'Com estoque'],
                'vazio' => 'Todos',
            ],
            [
                'tipo' => 'texto',
                'nome' => 'busca',
                'rotulo' => 'Busca',
                'valor' => $filtros['busca'],
                'placeholder' => 'Nome ou código',
            ],
            [
                'tipo' => 'checkbox',
                'nome' => 'apenas_critico',
                'rotulo' => 'Mostrar somente estoque crítico',
                'valor' => $filtros['apenas_critico'],
            ],
        ];
    }
}
