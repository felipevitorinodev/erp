<?php

namespace App\Services\Painel;

use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Support\CacheDados;
use App\Support\IndiceBanco;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Números da tela inicial. Tudo agregado no banco e guardado no cache entre telas: voltar ao início não
 * refaz as somas enquanto nada for gravado (qualquer venda, conta ou ajuste de estoque troca a versão).
 */
class PainelService
{
    /**
     * @return array<string, mixed> variáveis da view welcome
     */
    public function dados(User $usuario): array
    {
        $empresaId = (int) ($usuario->empresa_id ?? 0);
        $podeRelatorios = $usuario->pode('relatorios');
        $podeFinanceiro = $usuario->pode('financeiro');
        $hoje = now()->toDateString();

        return CacheDados::lembrar(
            'painel',
            ['vendas', 'financeiro', 'estoque'],
            [$hoje, $podeRelatorios, $podeFinanceiro],
            fn () => $this->calcular($empresaId, $hoje, $podeRelatorios, $podeFinanceiro),
            600,
            $empresaId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function calcular(int $empresaId, string $hoje, bool $podeRelatorios, bool $podeFinanceiro): array
    {
        $inicioMes = Carbon::parse($hoje)->startOfMonth();
        $fimMes = $inicioMes->copy()->endOfMonth();
        $inicioMesSql = $inicioMes->toDateString();
        $fimMesSql = $fimMes->toDateString();

        $dados = [
            'vendasHoje' => 0,
            'faturamentoHoje' => 0.0,
            'contasReceberVencidas' => 0,
            'contasPagarVencidas' => 0,
            'aReceber7Dias' => 0.0,
            'estoqueCritico' => 0,
            'faturamentoMes' => null,
            'contasMes' => null,
        ];

        if ($podeRelatorios) {
            // Uma leitura do mês (índice de relatório) serve o gráfico diário e os números de hoje.
            $porDia = [];
            foreach (IndiceBanco::usar(Venda::query(), 'vendas', 'vendas_relatorio_index')
                ->where('empresa_id', $empresaId)
                ->whereDataEntre('data_venda', $inicioMesSql, $fimMesSql)
                ->toBase()
                ->selectRaw('data_venda as dia')
                ->selectRaw('COUNT(*) as todas')
                ->selectRaw("SUM(CASE WHEN situacao = 'confirmada' THEN 1 ELSE 0 END) as qtd")
                ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'confirmada' THEN total END), 0) as total")
                ->groupBy('data_venda')
                ->get() as $linha) {
                $porDia[substr((string) $linha->dia, 0, 10)] = $linha;
            }

            $dados['vendasHoje'] = (int) ($porDia[$hoje]->todas ?? 0);
            $dados['faturamentoHoje'] = (float) ($porDia[$hoje]->total ?? 0);
            $dados['faturamentoMes'] = $this->faturamentoMes($porDia, $inicioMes, $fimMes, $hoje);

            // Índice (empresa_id, situacao, data_vencimento, deleted_at...): conta só as vencidas, sem ler as demais
            // contas da empresa (0,23 s para 0,11 s em 330 mil contas).
            $vencidas = function (string $modelo) use ($empresaId, $hoje): int {
                $tabela = (new $modelo)->getTable();

                return IndiceBanco::usar($modelo::query(), $tabela, $tabela.'_relatorio_index')
                    ->where('empresa_id', $empresaId)
                    ->whereIn('situacao', ['aberta', 'parcial'])
                    ->where('data_vencimento', '<', $hoje)
                    ->count();
            };

            $dados['contasReceberVencidas'] = $vencidas(ContaReceber::class);
            $dados['contasPagarVencidas'] = $vencidas(ContaPagar::class);

            $dados['estoqueCritico'] = Produto::query()
                ->where('empresa_id', $empresaId)
                ->where('controla_estoque', true)
                ->where('ativo', true)
                ->whereColumn('estoque_atual', '<=', 'estoque_minimo')
                ->count();
        }

        if ($podeFinanceiro) {
            $dados['aReceber7Dias'] = (float) ContaReceber::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('situacao', ['aberta', 'parcial'])
                ->whereDataEntre('data_vencimento', $hoje, Carbon::parse($hoje)->addDays(7)->toDateString())
                ->sum(DB::raw('valor - COALESCE(valor_pago, 0)'));

            $resumoConta = function (string $modelo) use ($empresaId, $inicioMesSql, $fimMesSql): array {
                $linha = $modelo::query()
                    ->where('empresa_id', $empresaId)
                    ->whereDataEntre('data_vencimento', $inicioMesSql, $fimMesSql)
                    ->where('situacao', '!=', 'cancelada')
                    ->toBase()
                    ->selectRaw('COALESCE(SUM(COALESCE(valor_pago, 0)), 0) as pago')
                    ->selectRaw("COALESCE(SUM(CASE WHEN situacao IN ('aberta', 'parcial') THEN valor - COALESCE(valor_pago, 0) ELSE 0 END), 0) as aberto")
                    ->first();

                return [
                    'pago' => round((float) ($linha->pago ?? 0), 2),
                    'aberto' => round((float) ($linha->aberto ?? 0), 2),
                ];
            };

            $dados['contasMes'] = [
                'receber' => $resumoConta(ContaReceber::class),
                'pagar' => $resumoConta(ContaPagar::class),
                'inicio' => $inicioMesSql,
                'fim' => $fimMesSql,
            ];
        }

        return $dados;
    }

    /**
     * @param  array<string, object>  $porDia
     * @return array<string, mixed>
     */
    private function faturamentoMes(array $porDia, Carbon $inicioMes, Carbon $fimMes, string $hoje): array
    {
        $dias = [];
        $totalMes = 0.0;
        $qtdMes = 0;
        $maximo = 0.0;
        $cursor = $inicioMes->copy();

        while ($cursor->lte($fimMes)) {
            $chave = $cursor->toDateString();
            $linha = $porDia[$chave] ?? null;
            $total = round((float) ($linha->total ?? 0), 2);
            $qtd = (int) ($linha->qtd ?? 0);
            $totalMes += $total;
            $qtdMes += $qtd;
            $maximo = max($maximo, $total);
            $dias[] = [
                'n' => $cursor->day,
                'total' => $total,
                'qtd' => $qtd,
                'hoje' => $chave === $hoje,
                'futuro' => $chave > $hoje,
                'eixo' => false,
            ];
            $cursor->addDay();
        }

        foreach ($dias as &$dia) {
            $dia['altura'] = $maximo > 0 ? round($dia['total'] / $maximo * 100, 1) : 0;
            $dia['eixo'] = $dia['n'] === 1 || $dia['n'] % 5 === 0 || $dia['hoje'];
        }
        unset($dia);

        foreach ($dias as $i => $dia) {
            if (! $dia['hoje']) {
                continue;
            }
            foreach ([-1, 1] as $vizinho) {
                $j = $i + $vizinho;
                if (isset($dias[$j]) && ! $dias[$j]['hoje']) {
                    $dias[$j]['eixo'] = false;
                }
            }
        }

        return [
            'dias' => $dias,
            'total' => round($totalMes, 2),
            'qtd' => $qtdMes,
            'ticket' => $qtdMes > 0 ? round($totalMes / $qtdMes, 2) : 0.0,
            'inicio' => $inicioMes->toDateString(),
            'fim' => $fimMes->toDateString(),
        ];
    }
}
