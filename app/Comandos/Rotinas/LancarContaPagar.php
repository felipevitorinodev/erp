<?php

namespace App\Comandos\Rotinas;

use App\Comandos\Achado;
use App\Comandos\Leitor;
use App\Http\Requests\ContasPagar\ContaPagarRequest;
use App\Services\ContasPagar\ContaPagarService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/** "pagar 1.200 ao fornecedor Atacado Sul dia 10", "conta de luz 230 vence sexta", "devo 500 ao Atacadão". */
final class LancarContaPagar extends LancamentoFinanceiro
{
    public function __construct(private readonly ContaPagarService $contas) {}

    public function chave(): string
    {
        return 'conta_pagar';
    }

    public function titulo(): string
    {
        return 'Conta a pagar';
    }

    public function atalho(): string
    {
        return 'conta a pagar';
    }

    public function exemplos(): array
    {
        return [
            'conta de luz 230 vence dia 10',
            'pagar 1.200 ao fornecedor Atacado Sul em 30 dias',
        ];
    }

    public function palavras(): array
    {
        return ['pagar', 'pagamento', 'despesa', 'despesas', 'boleto', 'fatura', 'devo', 'lancar', 'conta', 'contas', 'aluguel', 'energia', 'internet'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:lanc|cri|adicion|inclu|registr|cadastr)\w*\s+(?:(?:uma|a|nova)\s+)?)?(?:nova\s+)?(?:contas?\s+a\s+pagar|despesa|pagar)\b/u';
    }

    public function reconhecerNatural(string $texto): ?string
    {
        // "conta de luz 230 dia 10": a frase toda é a descrição; valor e data saem dela antes.
        if (preg_match('/^(?:conta|boleto|fatura|parcela)s?\s+(?:de|da|do|das|dos)\s+\D/u', Leitor::normalizar($texto))) {
            return 'referente a '.$texto;
        }

        // "devo 500 ao Atacadão"
        return Leitor::grupos('/^(?:devo|devemos)\s+(?<resto>.+)$/u', $texto)['resto'] ?? null;
    }

    protected function pessoa(): string
    {
        return 'fornecedor';
    }

    protected function campoPessoa(): string
    {
        return 'fornecedor_id';
    }

    protected function permissaoCadastro(): string
    {
        return 'fornecedores';
    }

    protected function buscarPessoa(string $nome): Achado
    {
        return $this->localizador()->fornecedor($nome);
    }

    protected function formulario(): string
    {
        return ContaPagarRequest::class;
    }

    protected function lancar(FormRequest $formulario): Model
    {
        return $this->contas->lancar($formulario);
    }

    protected function rotaDetalhe(): string
    {
        return 'conta-pagar.show';
    }

    protected function descricaoPadrao(string $nomePessoa): string
    {
        return 'Pagamento a '.$nomePessoa;
    }

    protected function sentido(): array
    {
        return ['a pagar', 'para'];
    }
}
