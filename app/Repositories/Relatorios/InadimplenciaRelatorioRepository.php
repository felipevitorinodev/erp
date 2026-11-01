<?php

namespace App\Repositories\Relatorios;

use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Relatorios\FaixaAtraso;
use App\Relatorios\SerieTemporal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InadimplenciaRelatorioRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(string $tipo, int $empresaId, array $filtros): Builder
    {
        $modelo = $tipo === 'pagar' ? ContaPagar::class : ContaReceber::class;
        $relacao = $tipo === 'pagar' ? 'fornecedor' : 'cliente';
        $hoje = now()->toDateString();

        $query = $modelo::query()
            ->with([$relacao => fn ($pessoa) => $pessoa->withTrashed()->select(['id', 'nome'])])
            ->where('empresa_id', $empresaId)
            ->whereIn('situacao', ['aberta', 'parcial'])
            ->where('data_vencimento', '<', $hoje);

        if (! empty($filtros['pessoa_id'])) {
            $query->where($tipo === 'pagar' ? 'fornecedor_id' : 'cliente_id', $filtros['pessoa_id']);
        }

        $faixa = $filtros['faixa'] ?? '';
        if ($faixa !== '') {
            $sql = FaixaAtraso::filtro('data_vencimento', $faixa);
            if ($sql) {
                $query->whereRaw($sql, [$hoje]);
            }
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
     * @param  array<string, mixed>  $filtros
     */
    public function ordenar(Builder $query, array $filtros): Builder
    {
        $direcao = ($filtros['direcao'] ?? '') === 'desc' ? 'desc' : 'asc';

        return match ($filtros['ordem'] ?? '') {
            'valor' => $query->orderBy('valor', $direcao)->orderBy('id'),
            'saldo' => $query->orderByRaw('(valor - valor_pago) '.$direcao)->orderBy('id'),
            'vencimento' => $query->orderBy('data_vencimento', $direcao)->orderBy('id'),
            default => $query->orderBy('data_vencimento')->orderBy('id'),
        };
    }

    /**
     * @return array{saldo: float, qtd: int, faixas: array<string, array{qtd: int, saldo: float}>}
     */
    public function totais(Builder $query): array
    {
        $hoje = now()->toDateString();
        $expressao = FaixaAtraso::expressao('data_vencimento');
        $consulta = (clone $query)->reorder()->toBase();
        $consulta->columns = [];
        $consulta->selectRaw($expressao.' as faixa', FaixaAtraso::ligacoes($hoje))
            ->selectRaw('(valor - valor_pago) as saldo');

        $porFaixa = [];
        foreach (DB::query()
            ->fromSub($consulta, 'atrasos')
            ->select('faixa')
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(saldo), 0) as saldo')
            ->groupBy('faixa')
            ->get() as $linha) {
            $porFaixa[(string) $linha->faixa] = [
                'qtd' => (int) $linha->qtd,
                'saldo' => round((float) $linha->saldo, 2),
            ];
        }

        $saldo = 0.0;
        $qtd = 0;
        $faixas = [];
        foreach (FaixaAtraso::FAIXAS as $faixa) {
            $item = $porFaixa[$faixa] ?? ['qtd' => 0, 'saldo' => 0.0];
            $faixas[$faixa] = $item;
            $saldo += $item['saldo'];
            $qtd += $item['qtd'];
        }

        return [
            'saldo' => round($saldo, 2),
            'qtd' => $qtd,
            'faixas' => $faixas,
        ];
    }

    /**
     * @return array{labels: list<string>, dados: list<float>, vazio: bool}
     */
    public function serie(Builder $query): array
    {
        $chave = SerieTemporal::chaveMes('data_vencimento');
        $consulta = (clone $query)->reorder()->toBase();
        $consulta->columns = [];

        $mapa = [];
        foreach ($consulta
            ->selectRaw($chave.' as chave')
            ->selectRaw('COALESCE(SUM(valor - valor_pago), 0) as total')
            ->groupByRaw($chave)
            ->get() as $linha) {
            $mapa[substr((string) $linha->chave, 0, 7)] = round((float) $linha->total, 2);
        }

        if ($mapa === []) {
            return ['labels' => [], 'dados' => [], 'vazio' => true];
        }

        $chaves = array_keys($mapa);
        sort($chaves);
        $inicio = Carbon::parse($chaves[0].'-01')->startOfMonth();
        $fim = Carbon::parse($chaves[array_key_last($chaves)].'-01')->startOfMonth();
        $quantidade = ($fim->year - $inicio->year) * 12 + ($fim->month - $inicio->month) + 1;
        $labels = [];
        $dados = [];

        if ($quantidade > 36) {
            foreach ($chaves as $mes) {
                $labels[] = Carbon::parse($mes.'-01')->format('m/Y');
                $dados[] = $mapa[$mes];
            }
        } else {
            $cursor = $inicio->copy();
            while ($cursor->lte($fim)) {
                $labels[] = $cursor->format('m/Y');
                $dados[] = round((float) ($mapa[$cursor->format('Y-m')] ?? 0), 2);
                $cursor->addMonth();
            }
        }

        return [
            'labels' => $labels,
            'dados' => $dados,
            'vazio' => array_sum($dados) == 0.0,
        ];
    }
}
