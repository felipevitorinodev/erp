<?php

namespace App\Services\Relatorios\Concerns;

use App\Relatorios\Periodo;
use App\Support\CatalogoRelatorios;
use Illuminate\Pagination\LengthAwarePaginator;

trait MontaRelatorio
{
    protected function empresaId(): int
    {
        return (int) (auth()->user()->empresa_id ?? 0);
    }

    protected function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected function variacao(float $atual, float $anterior): ?string
    {
        if ($anterior == 0.0) {
            return null;
        }

        $percentual = round(($atual - $anterior) / $anterior * 100, 1);
        $sinal = $percentual > 0 ? '+' : '';

        return $sinal.number_format($percentual, 1, ',', '.').'% vs período anterior';
    }

    protected function detalhe(string $texto, ?string $variacao): string
    {
        return $variacao ? $texto.' · '.$variacao : $texto;
    }

    /**
     * @return array{slug: string, titulo: string, descricao: string, categoria: string, rota: string}
     */
    protected function definicao(string $slug): array
    {
        $item = collect(CatalogoRelatorios::relatorios())->firstWhere('slug', $slug);
        $categoria = CatalogoRelatorios::categorias()[$item['categoria']];

        return [
            'slug' => $slug,
            'titulo' => $item['titulo'],
            'descricao' => $item['descricao'],
            'categoria' => $categoria['titulo'],
            'rota' => $item['rota'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $chaves
     * @return array<string, mixed>
     */
    protected function shell(string $slug, array $filtros, bool $comPeriodo, array $chaves): array
    {
        $definicao = $this->definicao($slug);
        $action = route($definicao['rota']);

        return [
            'definicao' => $definicao,
            'action' => $action,
            'limpar' => $action,
            'resumoUrl' => route($definicao['rota'].'.resumo'),
            'pdf' => route($definicao['rota'].'.pdf', request()->except('page')),
            'csv' => route($definicao['rota'].'.csv', request()->except('page')),
            'comPeriodo' => $comPeriodo,
            'filtros' => $filtros,
            'atalhos' => $comPeriodo ? Periodo::atalhos($action, $filtros, $filtros['periodo']) : [],
            'ativos' => Periodo::contar($filtros, $chaves, $comPeriodo),
        ];
    }

    /**
     * @param  list<array{nome: string, total: float}>  $fatias
     * @return array<string, mixed>
     */
    protected function graficoBarras(string $id, string $titulo, array $fatias, string $formato): array
    {
        return [
            'id' => $id,
            'titulo' => $titulo,
            'tipo' => 'bar',
            'formato' => $formato,
            'labels' => array_column($fatias, 'nome'),
            'series' => [[
                'nome' => $titulo,
                'dados' => array_map(fn (array $fatia) => $fatia['total'], $fatias),
            ]],
            'vazio' => array_sum(array_column($fatias, 'total')) == 0.0,
        ];
    }

    /**
     * @param  array{labels: list<string>, dados: list<float>, vazio: bool}  $serie
     * @return array<string, mixed>
     */
    protected function graficoLinha(string $id, string $titulo, array $serie, string $nome): array
    {
        return [
            'id' => $id,
            'titulo' => $titulo,
            'tipo' => 'line',
            'formato' => 'moeda',
            'labels' => $serie['labels'],
            'series' => [[
                'nome' => $nome,
                'dados' => $serie['dados'],
            ]],
            'vazio' => $serie['vazio'],
        ];
    }

    /**
     * @param  class-string  $modelo
     */
    protected function pertence(string $modelo, int $empresaId, ?int $id): ?int
    {
        if (! $id) {
            return null;
        }

        $existe = $modelo::query()->where('empresa_id', $empresaId)->whereKey($id)->exists();

        return $existe ? $id : null;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     */
    protected function paginarLista(array $linhas, int $porPagina = 20): LengthAwarePaginator
    {
        $pagina = LengthAwarePaginator::resolveCurrentPage();
        $fatia = array_slice($linhas, ($pagina - 1) * $porPagina, $porPagina);

        return new LengthAwarePaginator($fatia, count($linhas), $porPagina, $pagina, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }
}
