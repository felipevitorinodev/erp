<?php

namespace App\Repositories\Relatorios;

use App\Models\ContasReceber\ContaReceber;
use App\Support\IndiceBanco;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ClienteDebitoRelatorioRepository
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function consulta(int $empresaId, array $filtros): Builder
    {
        $query = ContaReceber::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('situacao', ['aberta', 'parcial']);

        if (! empty($filtros['cliente_id'])) {
            return $query->where('cliente_id', $filtros['cliente_id']);
        }

        // Sem cliente escolhido o MySQL lia todas as contas da empresa pelo índice de vencimento e descartava as
        // pagas (0,65 s em 330 mil contas); o índice que começa por situação lê só as abertas, já com os valores (0,24 s).
        return IndiceBanco::usar($query, 'contas_receber', 'contas_receber_relatorio_index');
    }

    /**
     * Saldo por cliente já com o nome, numa ida ao banco (agregação em tabela derivada + LEFT JOIN clientes).
     * Antes os nomes vinham em WHERE id IN (um id por devedor): com 57 mil devedores já eram 57 mil parâmetros,
     * perto do limite de 65.535 do MySQL, acima do qual o relatório quebraria (como acontecia no ranking).
     * Clientes excluídos continuam com o nome, como antes.
     *
     * @return list<array{cliente_id: ?int, nome: ?string, qtd: int, saldo: float, vencido: float}>
     */
    public function linhas(Builder $query, int $empresaId): array
    {
        $hoje = now()->toDateString();
        $agregado = (clone $query)->reorder()->toBase();
        $agregado->columns = [];
        $agregado->select('cliente_id')
            ->selectRaw('COUNT(*) as qtd')
            ->selectRaw('COALESCE(SUM(valor - valor_pago), 0) as saldo')
            ->selectRaw('COALESCE(SUM(CASE WHEN data_vencimento < ? THEN valor - valor_pago ELSE 0 END), 0) as vencido', [$hoje])
            ->groupBy('cliente_id')
            ->havingRaw('COALESCE(SUM(valor - valor_pago), 0) > 0');

        return DB::query()
            ->fromSub($agregado, 't')
            ->leftJoin('clientes as c', function ($join) use ($empresaId) {
                $join->on('c.id', '=', 't.cliente_id')->where('c.empresa_id', '=', $empresaId);
            })
            ->select('t.cliente_id', 't.qtd', 't.saldo', 't.vencido', 'c.nome')
            ->get()
            ->map(fn ($linha) => [
                'cliente_id' => $linha->cliente_id ? (int) $linha->cliente_id : null,
                'nome' => $linha->nome !== null ? (string) $linha->nome : null,
                'qtd' => (int) $linha->qtd,
                'saldo' => round((float) $linha->saldo, 2),
                'vencido' => round((float) $linha->vencido, 2),
            ])
            ->all();
    }
}
