<?php

namespace App\Relatorios;

use App\Support\CatalogoRelatorios;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class Periodo
{
    public function __construct(
        public readonly string $inicio,
        public readonly string $fim,
    ) {}

    public static function extrair(Request $request): self
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date'],
            'data_fim' => ['nullable', 'date'],
        ], [
            'data_inicio.date' => 'Informe uma data inicial válida.',
            'data_fim.date' => 'Informe uma data final válida.',
        ]);

        $inicio = ! empty($dados['data_inicio'])
            ? Carbon::parse($dados['data_inicio'])->toDateString()
            : now()->startOfMonth()->toDateString();

        $fim = ! empty($dados['data_fim'])
            ? Carbon::parse($dados['data_fim'])->toDateString()
            : now()->toDateString();

        if ($fim < $inicio) {
            throw ValidationException::withMessages([
                'data_fim' => 'A data final deve ser igual ou posterior à data inicial.',
            ]);
        }

        $inclusivo = Carbon::parse($inicio)->startOfDay()->diff(Carbon::parse($fim)->startOfDay())->days + 1;
        $historicoDeVencidos = $request->input('vencimento') === 'vencidos';

        if (! $historicoDeVencidos && $inclusivo > CatalogoRelatorios::PERIODO_MAXIMO_DIAS) {
            throw ValidationException::withMessages([
                'data_fim' => 'O período não pode passar de '.CatalogoRelatorios::PERIODO_MAXIMO_DIAS.' dias.',
            ]);
        }

        return new self($inicio, $fim);
    }

    public function porMes(): bool
    {
        return Carbon::parse($this->inicio)->startOfDay()->diff(Carbon::parse($this->fim)->startOfDay())->days > 62;
    }

    public function anterior(): self
    {
        $dias = Carbon::parse($this->inicio)->startOfDay()->diff(Carbon::parse($this->fim)->startOfDay())->days;
        $fim = Carbon::parse($this->inicio)->startOfDay()->subDay();

        return new self($fim->copy()->subDays($dias)->toDateString(), $fim->toDateString());
    }

    public function rotulo(): string
    {
        return Carbon::parse($this->inicio)->format('d/m/Y').' a '.Carbon::parse($this->fim)->format('d/m/Y');
    }

    public function ehPadrao(): bool
    {
        return $this->inicio === now()->startOfMonth()->toDateString()
            && $this->fim === now()->toDateString();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array{rotulo: string, url: string, ativo: bool}>
     */
    public static function atalhos(string $action, array $filtros, self $atual): array
    {
        $hoje = now();
        $opcoes = [
            'Hoje' => [$hoje->toDateString(), $hoje->toDateString()],
            '7 dias' => [$hoje->copy()->subDays(6)->toDateString(), $hoje->toDateString()],
            'Mês atual' => [$hoje->copy()->startOfMonth()->toDateString(), $hoje->toDateString()],
            'Mês anterior' => [
                $hoje->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                $hoje->copy()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'Ano' => [$hoje->copy()->startOfYear()->toDateString(), $hoje->toDateString()],
        ];

        $base = $filtros;
        unset($base['data_inicio'], $base['data_fim'], $base['periodo'], $base['page']);

        $atalhos = [];
        foreach ($opcoes as $rotulo => [$inicio, $fim]) {
            $atalhos[] = [
                'rotulo' => $rotulo,
                'url' => $action.'?'.http_build_query(self::limpos(array_merge($base, [
                    'data_inicio' => $inicio,
                    'data_fim' => $fim,
                ]))),
                'ativo' => $atual->inicio === $inicio && $atual->fim === $fim,
            ];
        }

        return $atalhos;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $chaves
     */
    public static function contar(array $filtros, array $chaves, bool $periodoConta): int
    {
        $total = 0;

        foreach ($chaves as $chave) {
            $valor = $filtros[$chave] ?? null;
            if ($valor !== null && $valor !== '' && $valor !== false) {
                $total++;
            }
        }

        $periodo = $filtros['periodo'] ?? null;
        if ($periodoConta && $periodo instanceof self && ! $periodo->ehPadrao()) {
            $total++;
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public static function limpos(array $params): array
    {
        unset($params['periodo'], $params['page']);

        return array_filter(
            $params,
            fn ($valor) => $valor !== null && $valor !== '' && $valor !== false
        );
    }
}
