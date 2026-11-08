<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\Leitor;
use App\Comandos\PeriodoFalado;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** "contas que vencem amanhã", "contas a receber de hoje", "buscar contas desta semana". */
final class ConsultarContas extends Rotina
{
    private const LIMITE = 8;

    /** O que pode vir logo depois de "contas", sem engolir "conta de luz". */
    private const QUANDO = 'hoje|ontem|anteontem|amanha|depois\s+de\s+amanha|esta|este|essa|esse|nesta|neste|nessa|nesse|desta|deste|dessa|desse|semana|mes|dia(?:\s+\d{1,2})?|\d{1,2}[\/-]|\d{1,3}\s+dias?';

    public function chave(): string
    {
        return 'consulta_contas';
    }

    public function permissao(): string
    {
        return 'financeiro';
    }

    public function titulo(): string
    {
        return 'Contas por data';
    }

    public function atalho(): string
    {
        return 'contas que vencem hoje';
    }

    public function exemplos(): array
    {
        return ['contas que vencem amanhã', 'contas a receber desta semana'];
    }

    public function palavras(): array
    {
        return ['contas', 'vencem', 'vencendo', 'vencimento', 'buscar', 'ontem', 'amanha'];
    }

    public function padrao(): string
    {
        $quando = self::QUANDO;

        return '/^(?:(?:consult|ver|mostr|busc|procur|list|pesquis)\w*\s+)(?:as\s+)?contas?\b'
            .'|^(?:as\s+)?contas?(?=\s+(?:a\s+(?:receber|pagar)\s+)?(?:que\s+)?(?:vencem|vencendo|vence|a\s+vencer|do\s+dia|entre|de\s+(?:'.$quando.')|em\s+(?:'.$quando.')))/u';
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
        [$tipo, $texto] = $this->tipoETexto($argumento);
        $periodo = $this->periodo(Leitor::aparar($texto));
        if ($periodo instanceof Resposta) {
            return $periodo;
        }

        $receber = $tipo !== 'pagar' ? $this->totais($this->baseReceber($periodo)) : null;
        $pagar = $tipo !== 'receber' ? $this->totais($this->basePagar($periodo)) : null;

        $quantidade = (int) ($receber->quantidade ?? 0) + (int) ($pagar->quantidade ?? 0);
        $soma = (float) ($receber->soma ?? 0) + (float) ($pagar->soma ?? 0);
        $pagas = (int) ($receber->pagas ?? 0) + (int) ($pagar->pagas ?? 0);

        $recorte = match ($tipo) {
            'receber' => ', a receber',
            'pagar' => ', a pagar',
            default => '',
        };

        if ($quantidade === 0) {
            return Resposta::info($periodo->rotulo().$recorte.': nenhuma conta com esse vencimento.');
        }

        $linhas = [
            ...$this->linhas($tipo !== 'pagar' ? $this->lista($this->baseReceber($periodo), 'cliente') : collect(), 'A receber', 'recebida'),
            ...$this->linhas($tipo !== 'receber' ? $this->lista($this->basePagar($periodo), 'fornecedor') : collect(), 'A pagar', 'paga'),
        ];
        usort($linhas, fn (array $a, array $b) => [$a['data'], $a['id']] <=> [$b['data'], $b['id']]);
        $mostradas = array_slice($linhas, 0, self::LIMITE);

        return Resposta::info(
            $periodo->rotulo().$recorte.': '.$quantidade.($quantidade === 1 ? ' conta, ' : ' contas, ').self::moeda($soma).'.',
            array_values(array_filter([
                $pagas > 0 ? $pagas.($pagas === 1 ? ' já quitada' : ' já quitadas') : null,
                $quantidade > self::LIMITE ? 'Mostrando as '.self::LIMITE.' primeiras.' : null,
            ])),
            array_map(fn (array $linha) => ['descricao' => $linha['descricao'], 'valor' => $linha['valor']], $mostradas),
            link: $this->link($tipo, $periodo, $receber, $pagar),
        );
    }

    public function executar(array $dados): Resposta
    {
        return $this->preparar('');
    }

    /** @return array{0: ?string, 1: string} */
    private function tipoETexto(string $argumento): array
    {
        $leitor = new Leitor($argumento);
        $receber = $leitor->extrair('/\ba\s+receber\b/u') !== null;
        $pagar = $leitor->extrair('/\ba\s+pagar\b/u') !== null;

        $tipo = match (true) {
            $receber && ! $pagar => 'receber',
            $pagar && ! $receber => 'pagar',
            default => null,
        };

        return [$tipo, $leitor->resto()];
    }

    /** @return PeriodoFalado|Resposta */
    private function periodo(string $texto): PeriodoFalado|Resposta
    {
        if ($texto === '') {
            return $this->perguntar('Qual vencimento?', 'periodo', PeriodoFalado::sugestoes());
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

    private function baseReceber(PeriodoFalado $periodo): Builder
    {
        return $this->base(ContaReceber::query(), $periodo);
    }

    private function basePagar(PeriodoFalado $periodo): Builder
    {
        return $this->base(ContaPagar::query(), $periodo);
    }

    private function base(Builder $consulta, PeriodoFalado $periodo): Builder
    {
        return $consulta
            ->where('empresa_id', $this->empresaId())
            ->where('situacao', '!=', 'cancelada')
            ->whereDataEntre('data_vencimento', $periodo->inicio->toDateString(), $periodo->fim->toDateString());
    }

    private function totais(Builder $consulta): object
    {
        return (clone $consulta)->toBase()
            ->selectRaw('COUNT(*) as quantidade')
            ->selectRaw('COALESCE(SUM(valor), 0) as soma')
            ->selectRaw("SUM(CASE WHEN situacao = 'paga' THEN 1 ELSE 0 END) as pagas")
            ->first();
    }

    private function lista(Builder $consulta, string $pessoa): \Illuminate\Support\Collection
    {
        return (clone $consulta)
            ->with($pessoa.':id,nome')
            ->orderBy('data_vencimento')
            ->orderBy('id')
            ->limit(self::LIMITE)
            ->get();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Model>  $contas
     * @return list<array{data: string, id: int, descricao: string, valor: string}>
     */
    private function linhas(\Illuminate\Support\Collection $contas, string $lado, string $quitada): array
    {
        $relacao = $lado === 'A receber' ? 'cliente' : 'fornecedor';

        return $contas->map(function (Model $conta) use ($lado, $quitada, $relacao) {
            $nome = $conta->{$relacao}?->nome;
            $situacao = $conta->situacao === 'paga' ? ' · '.$quitada : ($conta->situacao === 'parcial' ? ' · parcial' : '');

            return [
                'data' => $conta->data_vencimento->format('Y-m-d'),
                'id' => (int) $conta->getKey(),
                'descricao' => $lado.($nome ? ' · '.$nome : '').' · '.$conta->descricao.$situacao,
                'valor' => self::moeda((float) $conta->valor),
            ];
        })->all();
    }

    private function link(?string $tipo, PeriodoFalado $periodo, ?object $receber, ?object $pagar): ?array
    {
        $lado = $tipo ?? ((int) ($pagar->quantidade ?? 0) === 0 && (int) ($receber->quantidade ?? 0) > 0
            ? 'receber'
            : ((int) ($receber->quantidade ?? 0) === 0 && (int) ($pagar->quantidade ?? 0) > 0 ? 'pagar' : null));

        if ($lado === null) {
            return null;
        }

        $umDia = $periodo->inicio->equalTo($periodo->fim);
        if ($umDia) {
            $rota = $lado === 'pagar' ? 'conta-pagar.index' : 'conta-receber.index';

            return [
                'rotulo' => $lado === 'pagar' ? 'Abrir contas a pagar' : 'Abrir contas a receber',
                'url' => route($rota, ['data_vencimento' => $periodo->inicio->toDateString()]),
            ];
        }

        if (! $this->usuario()->pode('relatorios')) {
            return null;
        }

        $rota = $lado === 'pagar' ? 'relatorio.contas-pagar' : 'relatorio.contas-receber';

        return [
            'rotulo' => 'Abrir relatório',
            'url' => route($rota, [
                'data_inicio' => $periodo->inicio->toDateString(),
                'data_fim' => $periodo->fim->toDateString(),
            ]),
        ];
    }
}
