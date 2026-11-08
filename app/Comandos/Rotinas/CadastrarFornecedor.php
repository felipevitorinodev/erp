<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Http\Requests\Fornecedores\FornecedorRapidoRequest;
use App\Models\Fornecedores\Fornecedor;
use App\Repositories\Fornecedores\FornecedorRepository;
use Illuminate\Validation\ValidationException;

/** "cadastre fornecedor Distribuidora Sul cnpj 11.222.333/0001-81 telefone (11) 3333-4444" */
final class CadastrarFornecedor extends Rotina
{
    public function __construct(private readonly FornecedorRepository $fornecedores) {}

    public function chave(): string
    {
        return 'fornecedor';
    }

    public function permissao(): string
    {
        return 'fornecedores';
    }

    public function titulo(): string
    {
        return 'Cadastrar fornecedor';
    }

    public function atalho(): string
    {
        return 'cadastrar fornecedor';
    }

    public function exemplos(): array
    {
        return [
            'cadastrar fornecedor Distribuidora Sul',
            'novo fornecedor Atacado Bom Preço cnpj 11.222.333/0001-81 fone (11) 3333-4444',
        ];
    }

    public function palavras(): array
    {
        return ['cadastrar', 'cadastre', 'cadastra', 'cadastro', 'fornecedor', 'fornecedores', 'distribuidor', 'distribuidora'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:cadastr|cri|adicion|inclu|registr|salv)\w*\s+(?:(?:o|a|um|uma|novo|nova)\s+)?fornecedor|nov[oa]\s+fornecedor|fornecedor\s+nov[oa])\b/u';
    }

    /** "cadastra a Distribuidora Sul como fornecedor" */
    public function reconhecerNatural(string $texto): ?string
    {
        $grupos = Leitor::grupos('/^(?:cadastr|registr|salv|adicion|inclu|coloc|bot)\w*\s+(?:(?:o|a)\s+)?(?<nome>.+?)\s+como\s+(?:(?:um|nov[oa])\s+)?fornecedor\b(?<mais>.*)$/u', $texto);

        return $grupos === null ? null : trim($grupos['nome'].' '.$grupos['mais']);
    }

    public function preparar(string $argumento): Resposta
    {
        try {
            $dados = $this->ler($argumento);
        } catch (EntradaInvalida $e) {
            return $this->corrigirEntrada($e, 'Qual é o CNPJ?', [Resposta::opcao('Sem CNPJ', 'sem cnpj')]);
        }

        if ($dados['nome'] === '') {
            return $this->perguntar('Qual o nome do fornecedor?', 'nome');
        }
        if (mb_strlen($dados['nome']) < 2) {
            return $this->perguntar('Qual o nome completo do fornecedor?', 'nome', termo: $dados['nome']);
        }

        try {
            FormularioInterno::validar(FornecedorRapidoRequest::class, $dados);
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        $repetido = Fornecedor::query()
            ->where('empresa_id', $this->empresaId())
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($dados['nome'])])
            ->exists();

        return Resposta::confirmacao(
            $this->chave(),
            'Cadastrar o fornecedor '.$dados['nome'].'?',
            $dados,
            array_values(array_filter([implode(' · ', array_filter([
                $dados['cnpj'] ? 'CNPJ '.$dados['cnpj'] : null,
                $dados['telefone'],
            ]))])),
            avisos: $repetido ? ['Já existe um fornecedor com esse nome.'] : [],
        );
    }

    public function executar(array $dados): Resposta
    {
        try {
            $formulario = FormularioInterno::validar(FornecedorRapidoRequest::class, $dados);
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        $fornecedor = $this->fornecedores->cadastrarRapido($formulario->validated());

        return Resposta::sucesso(
            'Pronto! Fornecedor cadastrado: '.$fornecedor->nome.'.',
            ['rotulo' => 'Completar cadastro', 'url' => route('fornecedor.edit', $fornecedor)],
            registro: ['entidade' => 'fornecedor', 'id' => $fornecedor->id],
        );
    }

    /**
     * @return array{nome: string, cnpj: ?string, telefone: ?string}
     *
     * @throws EntradaInvalida
     */
    private function ler(string $argumento): array
    {
        $leitor = new Leitor($argumento);
        $contatos = $leitor->contatos();
        $documento = $contatos['documento'];

        if ($documento !== null && $documento['tipo'] !== 'cnpj') {
            throw new EntradaInvalida('Fornecedor é cadastrado com CNPJ, e esse número tem formato de CPF.', $contatos['documento_digitado']);
        }

        return [
            'nome' => mb_substr($leitor->resto(), 0, 200),
            'cnpj' => $documento['numero'] ?? null,
            'telefone' => $contatos['telefone'],
        ];
    }
}
