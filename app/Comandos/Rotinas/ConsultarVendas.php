<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\Leitor;
use App\Comandos\PeriodoFalado;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Models\Vendas\Venda;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** "vendas de ontem", "vendas de 01/11 a 05/11", "buscar vendas desta semana". "vendas de hoje" continua no resumo. */
final class ConsultarVendas extends Rotina
{
    private const LIMITE = 8;

    public function chave(): string
    {
        return 'consulta_venda';
    }

    public function permissao(): string
    {
        return 'vendas';
    }

    public function titulo(): string
    {
        return 'Vendas por data';
    }

    public function atalho(): string
    {
        return 'vendas de ontem';
    }

    public function exemplos(): array
    {
        return ['vendas de ontem', 'vendas de 01/11 a 05/11'];
    }

    public function palavras(): array
    {
        return ['vendas', 'ontem', 'anteontem', 'semana', 'periodo', 'buscar', 'intervalo'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:consult|ver|mostr|busc|procur|list|pesquis)\w*\s+(?:as\s+)?)?vendas(?:\s+(?:entre|de|do|da|em|no|na|para))?\b/u';
    }

    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if ($campo === 'periodo' && ! $this->entendePeriodo($resposta)) {
            return $argumento;
        }

        return parent::completar($argumento, $campo, $resposta, $termo);
    }

    public function preparar(string $argumento): Resposta
    {
        $periodo = $this->periodo(Leitor::aparar($argumento));
        if ($periodo instanceof Resposta) {
            return $periodo;
        }

        $consulta = $this->consulta($periodo);
        $resumo = (clone $consulta)->toBase()
            ->selectRaw("SUM(CASE WHEN situacao = 'confirmada' THEN 1 ELSE 0 END) as confirmadas")
            ->selectRaw("SUM(CASE WHEN situacao = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento")
            ->selectRaw("COALESCE(SUM(CASE WHEN situacao = 'confirmada' THEN total END), 0) as faturamento")
            ->first();

        $confirmadas = (int) ($resumo->confirmadas ?? 0);
        $emAndamento = (int) ($resumo->em_andamento ?? 0);
        $faturamento = (float) ($resumo->faturamento ?? 0);

        $vendas = (clone $consulta)->with('cliente:id,nome')->orderByDesc('id')->limit(self::LIMITE + 1)->get();
        $mostradas = $vendas->take(self::LIMITE);

        $texto = $confirmadas === 0
            ? $periodo->rotulo().': nenhuma venda confirmada.'
            : $periodo->rotulo().': '.self::moeda($faturamento).' em '.$confirmadas.($confirmadas === 1 ? ' venda.' : ' vendas.');

        return Resposta::info(
            $texto,
            array_values(array_filter([
                $emAndamento > 0 ? $emAndamento.($emAndamento === 1 ? ' venda em andamento' : ' vendas em andamento') : null,
                $vendas->count() > self::LIMITE ? 'Mostrando as '.self::LIMITE.' mais recentes.' : null,
            ])),
            $mostradas->map(function (Venda $venda) {
                $nome = $venda->cliente?->nome;
                $descricao = 'nº '.($venda->numero ?: $venda->id).($nome ? ' · '.$nome : '');
                if ($venda->situacao !== 'confirmada') {
                    $descricao .= ' · '.$this->rotuloSituacao($venda->situacao);
                }

                return ['descricao' => $descricao, 'valor' => self::moeda((float) $venda->total)];
            })->values()->all(),
            link: ['rotulo' => 'Abrir vendas', 'url' => route('venda.index', [
                'data_inicio' => $periodo->inicio->toDateString(),
                'data_fim' => $periodo->fim->toDateString(),
            ])],
        );
    }

    public function executar(array $dados): Resposta
    {
        return $this->preparar('');
    }

    private function consulta(PeriodoFalado $periodo): Builder
    {
        $consulta = Venda::query()
            ->where('empresa_id', $this->empresaId())
            ->whereDataEntre('data_venda', $periodo->inicio->toDateString(), $periodo->fim->toDateString());

        if (! $this->usuario()->pode('vendas.ver_canceladas')) {
            $consulta->whereIn('situacao', ['confirmada', 'em_andamento']);
        }

        return $consulta;
    }

    /** @return PeriodoFalado|Resposta */
    private function periodo(string $texto): PeriodoFalado|Resposta
    {
        if ($texto === '') {
            return $this->perguntar('De qual período?', 'periodo', PeriodoFalado::sugestoes());
        }

        try {
            $periodo = PeriodoFalado::interpretar($texto, CarbonImmutable::today());
        } catch (EntradaInvalida $e) {
            return $this->corrigirEntrada($e, 'Qual é a data certa?', PeriodoFalado::sugestoes());
        }

        return $periodo ?? $this->perguntar('Não entendi o período. Qual data?', 'periodo', PeriodoFalado::sugestoes(), $texto);
    }

    private function entendePeriodo(string $resposta): bool
    {
        try {
            return PeriodoFalado::interpretar($resposta, CarbonImmutable::today()) !== null;
        } catch (EntradaInvalida) {
            return true;
        }
    }

    private function rotuloSituacao(string $situacao): string
    {
        return match ($situacao) {
            'em_andamento' => 'em andamento',
            'cancelada' => 'cancelada',
            default => $situacao,
        };
    }
}
