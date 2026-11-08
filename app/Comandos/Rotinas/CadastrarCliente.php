<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Http\Requests\Clientes\ClienteRapidoRequest;
use App\Models\Clientes\Cliente;
use App\Repositories\Clientes\ClienteRepository;
use Illuminate\Validation\ValidationException;

/** "cadastre cliente Maria Souza cpf 529.982.247-25", "cadastra a Maria como cliente". */
final class CadastrarCliente extends Rotina
{
    public function __construct(private readonly ClienteRepository $clientes) {}

    public function chave(): string
    {
        return 'cliente';
    }

    public function permissao(): string
    {
        return 'clientes.balcao';
    }

    public function titulo(): string
    {
        return 'Cadastrar cliente';
    }

    public function atalho(): string
    {
        return 'cadastrar cliente';
    }

    public function exemplos(): array
    {
        return [
            'cadastrar cliente Maria Souza',
            'novo cliente João Lima cpf 529.982.247-25 fone (11) 98765-4321',
        ];
    }

    public function palavras(): array
    {
        return ['cadastrar', 'cadastre', 'cadastra', 'cadastro', 'cliente', 'clientes'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:cadastr|cri|adicion|inclu|registr|salv)\w*\s+(?:(?:o|a|um|uma|novo|nova)\s+)?cliente|nov[oa]\s+cliente|cliente\s+nov[oa])\b/u';
    }

    /** "cadastra a Maria Souza como cliente, fone 99999-0000" */
    public function reconhecerNatural(string $texto): ?string
    {
        $grupos = Leitor::grupos('/^(?:cadastr|registr|salv|adicion|inclu|coloc|bot)\w*\s+(?:(?:o|a)\s+)?(?<nome>.+?)\s+como\s+(?:(?:um|uma|nov[oa])\s+)?cliente\b(?<mais>.*)$/u', $texto);

        return $grupos === null ? null : trim($grupos['nome'].' '.$grupos['mais']);
    }

    public function preparar(string $argumento): Resposta
    {
        try {
            $dados = $this->ler($argumento);
        } catch (EntradaInvalida $e) {
            return $this->corrigirEntrada($e, 'Qual é o número certo?', [Resposta::opcao('Sem documento', 'sem documento')]);
        }

        if ($dados['nome'] === '') {
            return $this->perguntar('Qual o nome do cliente?', 'nome');
        }
        if (mb_strlen($dados['nome']) < 2) {
            return $this->perguntar('Qual o nome completo do cliente?', 'nome', termo: $dados['nome']);
        }

        try {
            FormularioInterno::validar(ClienteRapidoRequest::class, $dados);
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        $repetido = Cliente::query()
            ->where('empresa_id', $this->empresaId())
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($dados['nome'])])
            ->exists();

        return Resposta::confirmacao(
            $this->chave(),
            'Cadastrar o cliente '.$dados['nome'].'?',
            $dados,
            array_values(array_filter([self::contatos($dados)])),
            avisos: $repetido ? ['Já existe um cliente com esse nome.'] : [],
        );
    }

    public function executar(array $dados): Resposta
    {
        try {
            $formulario = FormularioInterno::validar(ClienteRapidoRequest::class, $dados);
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        $cliente = $this->clientes->cadastrarRapido($formulario->validated());

        return Resposta::sucesso(
            'Pronto! Cliente cadastrado: '.$cliente->nome.'.',
            ['rotulo' => 'Completar cadastro', 'url' => route('cliente.edit', $cliente)],
            registro: ['entidade' => 'cliente', 'id' => $cliente->id],
        );
    }

    /** "CPF 529.982.247-25 · (11) 98765-4321 · maria@x.com" */
    private static function contatos(array $dados): string
    {
        return implode(' · ', array_filter([
            $dados['cpf'] ? 'CPF '.$dados['cpf'] : null,
            $dados['cnpj'] ? 'CNPJ '.$dados['cnpj'] : null,
            $dados['telefone'],
            $dados['email'],
        ]));
    }

    /**
     * @return array{nome: string, cpf: ?string, cnpj: ?string, telefone: ?string, email: ?string}
     *
     * @throws EntradaInvalida
     */
    private function ler(string $argumento): array
    {
        $leitor = new Leitor($argumento);
        $contatos = $leitor->contatos();
        $documento = $contatos['documento'];

        return [
            'nome' => mb_substr($leitor->resto(), 0, 200),
            'cpf' => ($documento['tipo'] ?? null) === 'cpf' ? $documento['numero'] : null,
            'cnpj' => ($documento['tipo'] ?? null) === 'cnpj' ? $documento['numero'] : null,
            'telefone' => $contatos['telefone'],
            'email' => $contatos['email'],
        ];
    }
}
