<?php

namespace App\Repositories\Relatorios;

use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContaRelatorioRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(string $tipo, int $empresaId, Periodo $periodo, array $filtros): Builder
    {
        $modelo = $tipo === 'pagar' ? ContaPagar::class : ContaReceber::class;
        $relacao = $tipo === 'pagar' ? 'fornecedor' : 'cliente';

        $query = $modelo::query()
            ->with([$relacao.':id,nome', 'categoria:id,nome'])
            ->where('empresa_id', $empresaId)
            ->whereDataEntre('data_vencimento', $periodo->inicio, $periodo->fim);

        $situacao = $filtros['situacao'] ?? '';
        if ($situacao === 'em_aberto' || $situacao === 'pendente') {
            $query->whereIn('situacao', ['aberta', 'parcial']);
        } elseif ($situacao !== '') {
            $query->where('situacao', $situacao);
        }

        if (! empty($filtros['categoria_id'])) {
            $query->where('categoria_id', $filtros['categoria_id']);
        }

        if (! empty($filtros['pessoa_id'])) {
            $coluna = $tipo === 'pagar' ? 'fornecedor_id' : 'cliente_id';
            $query->where($coluna, $filtros['pessoa_id']);
        }

        $vencimento = $filtros['vencimento'] ?? '';
        if ($vencimento === 'vencidos') {
            $query->where('data_vencimento', '<', now()->toDateString())
                ->whereNotIn('situacao', ['paga', 'cancelada']);
        } elseif ($vencimento === 'a_vencer') {
            $query->where('data_vencimento', '>=', now()->toDateString())
                ->whereNotIn('situacao', ['paga', 'cancelada']);
        }

        if (($filtros['busca'] ?? '') !== '') {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca, $relacao) {
                $q->where('descricao', 'like', "%{$busca}%")
                    ->orWhereHas($relacao, fn ($pessoa) => $pessoa->where('nome', 'like', "%{$busca}%"));
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function totais(Builder $query): array
    {
        $hoje = now()->toDateString();
        $somavel = request('situacao') === 'cancelada' ? '1 = 1' : "situacao <> 'cancelada'";
        $emAberto = "situacao IN ('aberta', 'parcial')";

        $porCategoria = (clone $query)->toBase()
            ->select('categoria_id')
            ->selectRaw('COUNT(*) as registros')
            ->selectRaw("SUM(CASE WHEN {$somavel} THEN 1 ELSE 0 END) as qtd")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$somavel} THEN valor END), 0) as soma_valor")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$somavel} THEN valor_pago END), 0) as soma_pago")
            ->selectRaw("SUM(CASE WHEN {$emAberto} THEN 1 ELSE 0 END) as qtd_aberto")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$emAberto} THEN valor - valor_pago END), 0) as saldo_aberto")
            ->selectRaw("SUM(CASE WHEN {$emAberto} AND data_vencimento < ? THEN 1 ELSE 0 END) as qtd_vencido", [$hoje])
            ->selectRaw("COALESCE(SUM(CASE WHEN {$emAberto} AND data_vencimento < ? THEN valor - valor_pago END), 0) as saldo_vencido", [$hoje])
            ->groupBy('categoria_id')
            ->get();

        $somaveis = $porCategoria->filter(fn ($linha) => (int) $linha->qtd > 0);

        $nomes = CategoriaFinanceira::query()
            ->whereIn('id', $somaveis->pluck('categoria_id')->filter())
            ->pluck('nome', 'id');

        $subtotais = $somaveis
            ->groupBy(fn ($linha) => $linha->categoria_id ? ($nomes[$linha->categoria_id] ?? 'Sem categoria') : 'Sem categoria')
            ->map(fn ($grupo, $nome) => [
                'nome' => $nome,
                'soma_valor' => round((float) $grupo->sum('soma_valor'), 2),
                'soma_pago' => round((float) $grupo->sum('soma_pago'), 2),
            ])
            ->sortKeys();

        $somaValor = round((float) $porCategoria->sum('soma_valor'), 2);
        $somaPago = round((float) $porCategoria->sum('soma_pago'), 2);

        return [
            'registros' => (int) $porCategoria->sum('registros'),
            'qtd' => (int) $porCategoria->sum('qtd'),
            'soma_valor' => $somaValor,
            'soma_pago' => $somaPago,
            'percentual_pago' => $somaValor > 0 ? round($somaPago / $somaValor * 100, 1) : 0.0,
            'qtd_aberto' => (int) $porCategoria->sum('qtd_aberto'),
            'saldo_aberto' => round((float) $porCategoria->sum('saldo_aberto'), 2),
            'qtd_vencido' => (int) $porCategoria->sum('qtd_vencido'),
            'saldo_vencido' => round((float) $porCategoria->sum('saldo_vencido'), 2),
            'subtotais' => $subtotais,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function ordenar(Builder $query, array $filtros): Builder
    {
        $colunas = [
            'vencimento' => 'data_vencimento',
            'valor' => 'valor',
            'pago' => 'valor_pago',
            'descricao' => 'descricao',
            'situacao' => 'situacao',
        ];

        $ordem = $filtros['ordem'] ?? '';
        if ($ordem !== '' && isset($colunas[$ordem])) {
            $direcao = ($filtros['direcao'] ?? '') === 'desc' ? 'desc' : 'asc';

            return $query->orderBy($colunas[$ordem], $direcao)->orderBy('id');
        }

        return $query->orderBy('data_vencimento')->orderBy('id');
    }

    /**
     * @return array<string, float>
     */
    public function serie(Builder $query, Periodo $periodo): array
    {
        $somavel = request('situacao') === 'cancelada' ? '1 = 1' : "situacao <> 'cancelada'";
        $consulta = (clone $query)->toBase();
        $consulta->columns = [];

        if ($periodo->porMes()) {
            $chave = SerieTemporal::chaveMes('data_vencimento');
            $linhas = $consulta
                ->selectRaw($chave.' as chave')
                ->selectRaw("COALESCE(SUM(CASE WHEN {$somavel} THEN valor END), 0) as total")
                ->groupByRaw($chave)
                ->get();
            $tamanho = 7;
        } else {
            $linhas = $consulta
                ->selectRaw('data_vencimento as chave')
                ->selectRaw("COALESCE(SUM(CASE WHEN {$somavel} THEN valor END), 0) as total")
                ->groupBy('data_vencimento')
                ->get();
            $tamanho = 10;
        }

        $mapa = [];
        foreach ($linhas as $linha) {
            $mapa[substr((string) $linha->chave, 0, $tamanho)] = (float) $linha->total;
        }

        return $mapa;
    }

    /**
     * @param  Collection<string, array{nome: string, soma_valor: float, soma_pago: float}>  $subtotais
     * @return list<array{nome: string, total: float}>
     */
    public function categoriasGrafico(Collection $subtotais): array
    {
        return $subtotais
            ->sortByDesc('soma_valor')
            ->take(8)
            ->map(fn (array $linha) => [
                'nome' => $linha['nome'],
                'total' => $linha['soma_valor'],
            ])
            ->values()
            ->all();
    }
}
