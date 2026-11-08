<?php

namespace App\Comandos\Rotinas;

use App\Comandos\Achado;
use App\Comandos\Leitor;
use App\Http\Requests\ContasReceber\ContaReceberRequest;
use App\Services\ContasReceber\ContaReceberService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/** "receber 150 do cliente Maria vence 15/11", "a Maria me deve 150 para dia 10", "cobrar 80 do João". */
final class LancarContaReceber extends LancamentoFinanceiro
{
    public function __construct(private readonly ContaReceberService $contas) {}

    public function chave(): string
    {
        return 'conta_receber';
    }

    public function titulo(): string
    {
        return 'Conta a receber';
    }

    public function atalho(): string
    {
        return 'conta a receber';
    }

    public function exemplos(): array
    {
        return [
            'a Maria me deve 150 para dia 10',
            'receber 89,90 amanhã referente a mensalidade',
        ];
    }

    public function palavras(): array
    {
        return ['receber', 'recebimento', 'cobrar', 'cobranca', 'deve', 'devendo', 'fiado', 'pendurou', 'lancar', 'conta', 'contas'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:lanc|cri|adicion|inclu|registr|cadastr)\w*\s+(?:(?:uma|a|nova)\s+)?)?(?:nova\s+)?(?:contas?\s+a\s+receber|recebimento|receber)\b/u';
    }

    public function reconhecerNatural(string $texto): ?string
    {
        // "a Maria me deve 150 dia 10", "o João ficou devendo 50", "a Maria vai me pagar 80 sexta"
        $devendo = Leitor::grupos(
            '/^(?:(?:o|a)\s+)?(?:cliente\s+)?(?<pessoa>[^\d,;]+?)\s+(?:(?:me|nos)\s+)?(?:deve|devia|ficou\s+devendo|esta\s+devendo|ta\s+devendo|vai\s+(?:me\s+|nos\s+)?pagar|pendurou)(?:\s+(?<resto>.*))?$/u',
            $texto,
        );
        if ($devendo !== null) {
            return trim(($devendo['resto'] ?? '').', cliente '.$devendo['pessoa']);
        }

        // "cobrar 150 da Maria", "cobrança de 80 do João"
        return Leitor::grupos('/^(?:cobr(?:ar|e|a)|cobranca(?:\s+de)?)\s+(?<resto>.+)$/u', $texto)['resto'] ?? null;
    }

    protected function pessoa(): string
    {
        return 'cliente';
    }

    protected function campoPessoa(): string
    {
        return 'cliente_id';
    }

    protected function permissaoCadastro(): string
    {
        return 'clientes.balcao';
    }

    protected function buscarPessoa(string $nome): Achado
    {
        return $this->localizador()->cliente($nome);
    }

    protected function formulario(): string
    {
        return ContaReceberRequest::class;
    }

    protected function lancar(FormRequest $formulario): Model
    {
        return $this->contas->lancar($formulario);
    }

    protected function rotaDetalhe(): string
    {
        return 'conta-receber.show';
    }

    protected function descricaoPadrao(string $nomePessoa): string
    {
        return 'Recebimento de '.$nomePessoa;
    }

    protected function sentido(): array
    {
        return ['a receber', 'de'];
    }
}
