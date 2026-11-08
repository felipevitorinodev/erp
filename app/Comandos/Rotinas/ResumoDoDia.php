<?php

namespace App\Comandos\Rotinas;

use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Models\Vendas\Venda;

/** "quanto vendi hoje?", "como estão as vendas?": mesmos números do início, sem gravar nada. */
final class ResumoDoDia extends Rotina
{
    public function chave(): string
    {
        return 'resumo';
    }

    public function permissao(): string
    {
        return 'relatorios';
    }

    public function titulo(): string
    {
        return 'Vendas de hoje';
    }

    public function atalho(): string
    {
        return 'vendas de hoje';
    }

    public function exemplos(): array
    {
        return ['quanto vendi hoje?', 'como estão as vendas?'];
    }

    public function palavras(): array
    {
        return ['resumo', 'faturamento', 'faturei', 'faturado', 'vendi', 'vendemos', 'vendas', 'hoje', 'movimento', 'caixa', 'quanto', 'total'];
    }

    public function padrao(): string
    {
        return '/^(?:vendas?\s+(?:de\s+|do\s+dia\s+(?:de\s+)?)?hoje|vendas\s+do\s+dia(?!\s+\d)|resumo(?:\s+d[oe])?(?:\s+dia)?(?:\s+de\s+hoje)?'
            .'|quanto\s+(?:eu\s+|a\s+gente\s+|nos\s+)?(?:vendi|vendemos|faturei|faturamos|fiz|fizemos|entrou)(?:\s+hoje)?'
            .'|faturamento(?:\s+de)?(?:\s+hoje)?|total\s+(?:de\s+|das\s+)?vendas(?:\s+(?:de\s+)?hoje)?|movimento\s+(?:de\s+)?hoje'
            .'|como\s+(?:estao|tao|foram|esta|ta|foi)\s+(?:as\s+vendas|o\s+dia|o\s+movimento|o\s+caixa)(?:\s+(?:de\s+)?hoje)?)\b/u';
    }

    public function preparar(string $argumento): Resposta
    {
        $hoje = now()->toDateString();

        $resumo = Venda::query()
            ->where('empresa_id', $this->empresaId())
            ->whereDataEntre('data_venda', $hoje, $hoje)
            ->toBase()
            ->selectRaw("SUM(CASE WHEN situacao = 'confirmada' THEN 1 ELSE 0 END) as confirmadas")
            ->selectRaw("SUM(CASE WHEN situacao = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'confirmada' THEN total END), 0) as faturamento")
            ->first();

        $confirmadas = (int) ($resumo->confirmadas ?? 0);
        $emAndamento = (int) ($resumo->em_andamento ?? 0);
        $faturamento = (float) ($resumo->faturamento ?? 0);

        $texto = $confirmadas === 0
            ? 'Ainda não há vendas confirmadas hoje.'
            : 'Hoje: '.self::moeda($faturamento).' em '.$confirmadas.($confirmadas === 1 ? ' venda.' : ' vendas.');

        return Resposta::info(
            $texto,
            array_values(array_filter([
                $confirmadas > 0 ? 'Ticket médio de '.self::moeda(round($faturamento / $confirmadas, 2)) : null,
                $emAndamento > 0 ? $emAndamento.($emAndamento === 1 ? ' venda em andamento' : ' vendas em andamento') : null,
            ])),
            link: ['rotulo' => 'Abrir relatório', 'url' => route('relatorio.vendas', ['data_inicio' => $hoje, 'data_fim' => $hoje])],
        );
    }

    public function executar(array $dados): Resposta
    {
        return $this->preparar('');
    }
}
