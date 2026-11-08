<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\FormaNaFrase;
use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\PeriodoFalado;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Http\Requests\Orcamentos\OrcamentoRequest;
use App\Models\FormasPagamento\FormaPagamento;
use App\Services\Orcamentos\OrcamentoService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** "orçamento 2 coca para a Maria", "orçar 1 troca de tela no pix", "novo orçamento". */
final class RegistrarOrcamento extends Rotina
{
    public function __construct(private readonly OrcamentoService $orcamentos) {}

    public function chave(): string
    {
        return 'orcamento';
    }

    public function permissao(): string
    {
        return 'orcamentos';
    }

    public function titulo(): string
    {
        return 'Novo orçamento';
    }

    public function atalho(): string
    {
        return 'novo orçamento';
    }

    public function exemplos(): array
    {
        return [
            'orçamento 2 coca para a Maria',
            'orçar 1 troca de tela no pix',
        ];
    }

    public function palavras(): array
    {
        return ['orcamento', 'orcamentos', 'orcar', 'cotacao', 'cotar', 'proposta'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:faz|lanc|registr|cri|abr|mont)\w*\s+(?:(?:um|uma|o|a|novo|nova)\s+)?)?(?:nov[oa]\s+)?(?:orcamentos?|cotacoes?)\b|^(?:orcar|cotar)\b/u';
    }

    protected function conectores(): array
    {
        return ['itens' => ',', 'forma' => 'no', 'cliente' => 'cliente', 'data' => 'data'];
    }

    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if ($campo === 'forma' && $this->localizador()->formaPagamento($resposta)->nenhum()) {
            return $argumento;
        }
        if ($campo === 'cliente' && $this->respostaSem($resposta, 'cliente')) {
            return Leitor::limpar($argumento.' '.$resposta);
        }
        if ($campo === 'data' && ! $this->entendeData($resposta)) {
            return $argumento;
        }

        return parent::completar($argumento, $campo, $resposta, $termo);
    }

    public function preparar(string $argumento): Resposta
    {
        $leitor = new Leitor($argumento);

        try {
            $entrega = PeriodoFalado::extrairDaFrase($leitor, CarbonImmutable::today(), aceitaSolta: false);
            $dataDita = PeriodoFalado::extrairRotulo($leitor, 'data(?:\s+do\s+orcamento)?', CarbonImmutable::today())
                ?? $leitor->data(CarbonImmutable::today());
        } catch (EntradaInvalida $e) {
            return $this->corrigirEntrada($e, 'Qual é a data certa?', ['Hoje', 'Amanhã', 'Em 7 dias']);
        }

        [$forma, $problemaForma] = $this->formaInformada($leitor);
        if ($problemaForma !== null) {
            return $problemaForma;
        }
        if ($forma instanceof FormaPagamento) {
            FormaNaFrase::remover($leitor, $forma);
        }

        $semCliente = $leitor->extrair('/\bsem\s+cliente\b/u') !== null;
        [$cliente, $problemaCliente] = $semCliente ? [null, null] : $this->clienteInformado($leitor);

        $resto = $leitor->resto();
        if ($resto === '') {
            return $this->perguntar('O que entra no orçamento?', 'itens');
        }

        [$itens, $problemaItens] = $this->itensInformados($resto, 'O que entra no orçamento?', 'orçar');
        if ($problemaItens !== null) {
            return $problemaItens;
        }
        if ($problemaCliente !== null) {
            return $problemaCliente;
        }
        if ($cliente === null && ! $semCliente) {
            return $this->perguntar('Para qual cliente?', 'cliente', [Resposta::opcao('Sem cliente', 'sem cliente')]);
        }
        if ($dataDita === null) {
            return $this->perguntar('Qual a data do orçamento?', 'data', ['Hoje', 'Amanhã', 'Em 7 dias']);
        }

        $total = $this->totalDosItens($itens);
        $dados = [
            'cliente_id' => $cliente?->getKey(),
            'data_orcamento' => $dataDita->toDateString(),
            'data_informada' => true,
            'data_entrega' => $entrega?->toDateString(),
            'forma_pagamento' => $forma?->nome,
            'itens' => $this->itensParaGravar($itens),
        ];

        try {
            FormularioInterno::validar(OrcamentoRequest::class, $this->semMarcador($dados));
        } catch (ValidationException $e) {
            return $this->perguntaDoFormulario($e) ?? $this->erroDeValidacao($e);
        }

        return Resposta::confirmacao(
            $this->chave(),
            'Registrar orçamento de '.self::moeda($total).($forma ? ' no '.$forma->nome : '').($cliente ? ' para '.$cliente->nome : '').'?',
            $dados,
            array_values(array_filter([
                'Data: '.self::data($dataDita),
                $entrega ? 'Entrega em '.self::data($entrega) : null,
            ])),
            itens: $this->itensParaConfirmacao($itens),
        );
    }

    public function executar(array $dados): Resposta
    {
        if (empty($dados['data_informada'])) {
            $dados['data_orcamento'] = today()->toDateString();
        }

        try {
            $orcamento = $this->orcamentos->gravar(FormularioInterno::validar(OrcamentoRequest::class, $this->semMarcador($dados)));
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        return Resposta::sucesso(
            'Pronto! Orçamento nº '.$orcamento->numero.' registrado.',
            ['rotulo' => 'Ver o orçamento', 'url' => route('orcamento.show', $orcamento)],
            [self::moeda((float) $orcamento->total)],
            registro: ['entidade' => 'orcamento', 'id' => (int) $orcamento->getKey()],
        );
    }

    /** @param array<string, mixed> $dados */
    private function semMarcador(array $dados): array
    {
        unset($dados['data_informada']);

        return $dados;
    }

    private function entendeData(string $resposta): bool
    {
        try {
            return (new Leitor('data '.$resposta))->data(CarbonImmutable::today()) !== null;
        } catch (EntradaInvalida) {
            return true;
        }
    }

    private function perguntaDoFormulario(ValidationException $excecao): ?Resposta
    {
        if ($this->faltaCampo($excecao, 'itens')) {
            return $this->perguntar('O que entra no orçamento?', 'itens');
        }
        if ($this->faltaCampo($excecao, 'data_orcamento')) {
            return $this->perguntar('Qual a data do orçamento?', 'data', ['Hoje', 'Amanhã', 'Em 7 dias']);
        }

        return null;
    }
}
