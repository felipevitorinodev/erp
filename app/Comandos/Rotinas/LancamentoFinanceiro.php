<?php

namespace App\Comandos\Rotinas;

use App\Comandos\Achado;
use App\Comandos\EntradaInvalida;
use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * Conta a receber ou a pagar: "receber 150 do cliente Maria vence 15/11 referente a conserto".
 * O que faltar (valor, vencimento, descrição) é perguntado, um de cada vez.
 */
abstract class LancamentoFinanceiro extends Rotina
{
    /** "cliente" ou "fornecedor" */
    abstract protected function pessoa(): string;

    /** "cliente_id" ou "fornecedor_id" */
    abstract protected function campoPessoa(): string;

    /** Permissão para cadastrar a pessoa que não foi encontrada. */
    abstract protected function permissaoCadastro(): string;

    abstract protected function buscarPessoa(string $nome): Achado;

    /** @return class-string<FormRequest> */
    abstract protected function formulario(): string;

    abstract protected function lancar(FormRequest $formulario): Model;

    abstract protected function rotaDetalhe(): string;

    /** Descrição usada quando a frase só diz de quem é a conta. */
    abstract protected function descricaoPadrao(string $nomePessoa): string;

    /** @return array{0: string, 1: string} ["a receber", "de"] ou ["a pagar", "para"] */
    abstract protected function sentido(): array;

    public function permissao(): string
    {
        return 'financeiro';
    }

    protected function conectores(): array
    {
        return ['valor' => 'valor', 'vencimento' => 'vence', 'descricao' => 'referente a'];
    }

    /** Valor e data só entram na frase se forem entendidos; senão a mesma pergunta volta. */
    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if (in_array($campo, ['valor', 'vencimento'], true) && ! $this->entende($campo, $resposta)) {
            return $argumento;
        }

        return parent::completar($argumento, $campo, $resposta, $termo);
    }

    public function preparar(string $argumento): Resposta
    {
        $leitor = new Leitor($argumento);

        try {
            $vencimento = $leitor->data(CarbonImmutable::today());
            $valor = $leitor->valor();
        } catch (EntradaInvalida $e) {
            return $this->corrigirEntrada($e, 'Qual é a data certa?', ['Amanhã', 'Em 30 dias']);
        }

        [$pessoa, $descricao, $problemaPessoa] = $this->pessoaEDescricao($leitor);

        if ($valor === null) {
            return $this->perguntar('Qual o valor?', 'valor');
        }
        if ($vencimento === null) {
            return $this->perguntar('Quando vence?', 'vencimento', ['Hoje', 'Amanhã', 'Em 7 dias', 'Em 30 dias']);
        }
        if ($problemaPessoa !== null) {
            return $problemaPessoa;
        }
        if ($descricao === null) {
            return $this->perguntar('Do que é essa conta?', 'descricao');
        }

        $dados = [
            'descricao' => mb_substr($descricao, 0, 200),
            $this->campoPessoa() => $pessoa?->getKey(),
            'valor' => number_format($valor, 2, '.', ''),
            'data_vencimento' => $vencimento->toDateString(),
        ];

        try {
            FormularioInterno::validar($this->formulario(), $dados);
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        [$tipo, $preposicao] = $this->sentido();

        return Resposta::confirmacao(
            $this->chave(),
            'Lançar '.self::moeda($valor).' '.$tipo.($pessoa ? ' '.$preposicao.' '.$pessoa->nome : '').'?',
            $dados,
            [
                'Vence em '.self::data($vencimento).$this->quandoVence($vencimento),
                'Descrição: '.$dados['descricao'],
            ],
        );
    }

    public function executar(array $dados): Resposta
    {
        try {
            $conta = $this->lancar(FormularioInterno::validar($this->formulario(), $dados));
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        [$tipo] = $this->sentido();

        return Resposta::sucesso(
            'Pronto! Conta '.$tipo.' lançada.',
            ['rotulo' => 'Abrir a conta', 'url' => route($this->rotaDetalhe(), $conta)],
            [self::moeda((float) $conta->valor).' · vence em '.self::data($conta->data_vencimento)],
            registro: ['entidade' => $this->chave(), 'id' => (int) $conta->getKey()],
        );
    }

    /**
     * Pessoa com rótulo ("cliente Maria") ou, sem rótulo, o resto da frase quando ele é o nome
     * de uma pessoa cadastrada. O que não for pessoa vira descrição.
     *
     * @return array{0: ?Model, 1: ?string, 2: ?Resposta}
     */
    private function pessoaEDescricao(Leitor $leitor): array
    {
        $nomeRotulado = $leitor->rotulado($this->pessoa());
        $descricao = $leitor->descricao();
        $resto = $leitor->resto();

        if ($nomeRotulado !== null) {
            $achado = $this->buscarPessoa($nomeRotulado);
            $problema = $this->problemaNaBusca($achado, 'o '.$this->pessoa(), 'pessoa', $this->opcoesSemPessoa($nomeRotulado));
            $descricao ??= $resto !== '' ? $resto : ($achado->registro ? $this->descricaoPadrao($achado->registro->nome) : null);

            return [$achado->registro, $descricao, $problema];
        }

        if ($resto !== '' && $descricao === null) {
            $achado = $this->buscarPessoa($resto);
            if ($achado->encontrou()) {
                return [$achado->registro, $this->descricaoPadrao($achado->registro->nome), null];
            }
            // "cobrar 150 da Maria" com duas Marias: pergunta qual. Texto maior é descrição.
            if (! $achado->nenhum() && count(explode(' ', $resto)) <= 3) {
                return [null, null, $this->problemaNaBusca($achado, 'o '.$this->pessoa(), 'pessoa')];
            }

            return [null, $resto, null];
        }

        return [null, $descricao, null];
    }

    /** @return list<array{rotulo: string, enviar: string}> */
    private function opcoesSemPessoa(string $nome): array
    {
        $opcoes = [Resposta::opcao('Sem '.$this->pessoa(), 'sem '.$this->pessoa())];
        if ($this->usuario()->pode($this->permissaoCadastro())) {
            $opcoes[] = Resposta::opcao('Cadastrar “'.$nome.'”', 'cadastrar '.$this->pessoa().' '.$nome);
        }

        return $opcoes;
    }

    private function entende(string $campo, string $resposta): bool
    {
        $leitor = new Leitor($this->conectores()[$campo].' '.$resposta);

        try {
            return $campo === 'valor'
                ? $leitor->valor() !== null
                : $leitor->data(CarbonImmutable::today()) !== null;
        } catch (EntradaInvalida) {
            return true; // data que não existe: a rotina explica o erro e pergunta a certa
        }
    }

    private function quandoVence(CarbonImmutable $vencimento): string
    {
        $dias = (int) CarbonImmutable::today()->diffInDays($vencimento);

        return match (true) {
            $dias === 0 => ' (hoje)',
            $dias === 1 => ' (amanhã)',
            default => ' (em '.$dias.' dias)',
        };
    }
}
