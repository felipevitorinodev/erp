<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\FormaNaFrase;
use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\PeriodoFalado;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Http\Requests\OrdensServico\OrdemServicoRequest;
use App\Models\FormasPagamento\FormaPagamento;
use App\Services\OrdensServico\OrdemServicoService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * "nova ordem de serviço notebook da Maria, não liga",
 * "abrir os celular cliente João defeito tela quebrada previsão sexta".
 */
final class AbrirOrdemServico extends Rotina
{
    public function __construct(private readonly OrdemServicoService $ordens) {}

    public function chave(): string
    {
        return 'ordem_servico';
    }

    public function permissao(): string
    {
        return 'ordens_servico';
    }

    public function titulo(): string
    {
        return 'Nova ordem de serviço';
    }

    public function atalho(): string
    {
        return 'nova ordem de serviço';
    }

    public function exemplos(): array
    {
        return [
            'nova ordem de serviço notebook da Maria, não liga',
            'abrir os celular cliente João defeito tela quebrada',
        ];
    }

    public function palavras(): array
    {
        return ['ordem', 'equipamento', 'aparelho', 'oficina', 'servico'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:abr|lanc|registr|cri|faz|mont)\w*\s+(?:(?:uma|a|nova)\s+)?)?(?:nova\s+)?ordem\s+de\s+servicos?\b'
            .'|^(?:(?:abr|cri|registr|lanc|faz)\w*\s+(?:uma\s+|a\s+)?)?nova\s+os\b'
            .'|^(?:abr|cri|registr)\w*\s+os\b/u';
    }

    protected function conectores(): array
    {
        return ['forma' => 'no', 'equipamento' => 'equipamento', 'abertura' => 'abertura', 'defeito' => 'defeito', 'cliente' => 'cliente'];
    }

    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if ($campo === 'forma' && $this->localizador()->formaPagamento($resposta)->nenhum()) {
            return $argumento;
        }
        if ($campo === 'equipamento' && Leitor::aparar($resposta) === '') {
            return $argumento;
        }
        if ($campo === 'defeito' && Leitor::aparar($resposta) === '') {
            return $argumento;
        }
        if ($campo === 'abertura' && ! $this->entendeAbertura($resposta)) {
            return $argumento;
        }
        if (in_array($campo, ['cliente', 'defeito'], true) && $this->respostaSem($resposta, $campo)) {
            return Leitor::limpar($argumento.' '.$resposta);
        }

        return parent::completar($argumento, $campo, $resposta, $termo);
    }

    public function preparar(string $argumento): Resposta
    {
        $leitor = new Leitor($argumento);

        try {
            $aberturaDita = PeriodoFalado::extrairRotulo($leitor, 'abertura|aberta|aberto', CarbonImmutable::today());
            $previsao = PeriodoFalado::extrairDaFrase($leitor, CarbonImmutable::today());
        } catch (EntradaInvalida $e) {
            return $this->corrigirEntrada($e, 'Qual é a data certa?', ['Hoje', 'Amanhã', 'Em 7 dias']);
        }

        if ($previsao !== null && $previsao->lt($aberturaDita ?? CarbonImmutable::today())) {
            return Resposta::erro('A previsão não pode ser anterior à abertura.');
        }

        [$forma, $problemaForma] = $this->formaInformada($leitor);
        if ($problemaForma !== null) {
            return $problemaForma;
        }
        if ($forma instanceof FormaPagamento) {
            FormaNaFrase::remover($leitor, $forma);
        }

        $semCliente = $leitor->extrair('/\bsem\s+cliente\b/u') !== null;
        [$cliente, $problemaCliente] = $semCliente ? [null, null] : $this->clienteInformado($leitor, aceitaDe: true);

        $semDefeito = $leitor->extrair('/\bsem\s+defeito\b/u') !== null;
        if ($semDefeito) {
            $leitor->extrair('/\bdefeito\b/u');
        }
        $defeito = $semDefeito ? null : $this->defeito($leitor);
        $equipamento = $leitor->rotulado('equipamento|aparelho');
        [$livre, $defeitoLivre, $partesDeItem] = $this->separar($leitor->resto());

        if ($equipamento === null) {
            $equipamento = $livre;
            if (! $semDefeito) {
                $defeito ??= $defeitoLivre;
            }
        } elseif (! $semDefeito && $defeito === null) {
            $extra = Leitor::aparar(trim(($livre ?? '').' '.($defeitoLivre ?? '')));
            $defeito = $extra !== '' ? $extra : null;
        }

        $itens = [];
        if ($partesDeItem !== []) {
            [$itens, $problemaItens] = $this->itensInformados(implode(', ', $partesDeItem), null, 'abrir a ordem');
            if ($problemaItens !== null) {
                return $problemaItens;
            }
        }
        if ($problemaCliente !== null) {
            return $problemaCliente;
        }
        if ($equipamento === null || $equipamento === '') {
            return $this->perguntar('Qual o equipamento?', 'equipamento');
        }
        if ($aberturaDita === null) {
            return $this->perguntar('Qual a data de abertura?', 'abertura', ['Hoje', 'Ontem']);
        }
        if ($defeito === null && ! $semDefeito) {
            return $this->perguntar('Qual o defeito relatado?', 'defeito', [Resposta::opcao('Sem defeito', 'sem defeito')]);
        }
        if ($cliente === null && ! $semCliente) {
            return $this->perguntar('Para qual cliente?', 'cliente', [Resposta::opcao('Sem cliente', 'sem cliente')]);
        }

        $equipamento = mb_substr($equipamento, 0, 150);
        $total = $this->totalDosItens($itens);
        $dados = [
            'cliente_id' => $cliente?->getKey(),
            'data_abertura' => $aberturaDita->toDateString(),
            'data_informada' => true,
            'data_previsao' => $previsao?->toDateString(),
            'equipamento' => $equipamento,
            'defeito_relatado' => $defeito !== null ? mb_substr($defeito, 0, 5000) : null,
            'forma_pagamento' => $forma?->nome,
            'itens' => $this->itensParaGravar($itens),
        ];

        try {
            FormularioInterno::validar(OrdemServicoRequest::class, $this->semMarcador($dados));
        } catch (ValidationException $e) {
            if ($this->faltaCampo($e, 'equipamento')) {
                return $this->perguntar('Qual o equipamento?', 'equipamento');
            }
            if ($this->faltaCampo($e, 'data_abertura')) {
                return $this->perguntar('Qual a data de abertura?', 'abertura', ['Hoje', 'Ontem']);
            }

            return $this->erroDeValidacao($e);
        }

        return Resposta::confirmacao(
            $this->chave(),
            'Abrir ordem de serviço do '.$equipamento
                .($cliente ? ' para '.$cliente->nome : '')
                .($itens !== [] ? ', no total de '.self::moeda($total) : '')
                .'?',
            $dados,
            array_values(array_filter([
                'Abertura: '.self::data($aberturaDita),
                $defeito ? 'Defeito: '.$defeito : null,
                $previsao ? 'Previsão: '.self::data($previsao) : null,
                $forma ? 'Pagamento: '.$forma->nome : null,
            ])),
            itens: $this->itensParaConfirmacao($itens),
            avisos: $itens === [] ? ['Sem itens lançados.'] : [],
        );
    }

    public function executar(array $dados): Resposta
    {
        if (empty($dados['data_informada'])) {
            $dados['data_abertura'] = today()->toDateString();
        }

        try {
            $os = $this->ordens->gravar(FormularioInterno::validar(OrdemServicoRequest::class, $this->semMarcador($dados)));
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        return Resposta::sucesso(
            'Pronto! Ordem de serviço nº '.$os->numero.' aberta.',
            ['rotulo' => 'Abrir a ordem', 'url' => route('ordem-servico.show', $os)],
            array_values(array_filter([
                $os->equipamento,
                (float) $os->total > 0 ? self::moeda((float) $os->total) : null,
            ])),
            registro: ['entidade' => 'ordem_servico', 'id' => (int) $os->getKey()],
        );
    }

    /** @param array<string, mixed> $dados */
    private function semMarcador(array $dados): array
    {
        unset($dados['data_informada']);

        return $dados;
    }

    private function entendeAbertura(string $resposta): bool
    {
        try {
            return (new Leitor('abertura '.$resposta))->data(CarbonImmutable::today()) !== null
                || PeriodoFalado::dataUnica($resposta, CarbonImmutable::today()) !== null;
        } catch (EntradaInvalida) {
            return true;
        }
    }

    private function defeito(Leitor $leitor): ?string
    {
        $grupos = $leitor->extrair('/\b(?:defeito|problema|com\s+defeito)\s+(?<texto>.+?)(?=\s*,|$|\s+(?:cliente|previsao|entrega|equipamento|aparelho)\b)/u');
        $texto = $grupos === null ? '' : Leitor::aparar($grupos['texto']);

        return $texto !== '' ? $texto : null;
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: list<string>}
     */
    private function separar(string $resto): array
    {
        $livres = [];
        $itens = [];

        foreach (preg_split('/\s*,\s*/u', $resto) ?: [] as $parte) {
            $parte = Leitor::aparar($parte);
            if ($parte === '') {
                continue;
            }
            if ($this->pareceItem($parte)) {
                $itens[] = $parte;
            } else {
                $livres[] = $parte;
            }
        }

        return [
            $livres[0] ?? null,
            count($livres) > 1 ? implode(', ', array_slice($livres, 1)) : null,
            $itens,
        ];
    }
}
