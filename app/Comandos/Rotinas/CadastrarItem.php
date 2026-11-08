<?php

namespace App\Comandos\Rotinas;

use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Http\Requests\Produtos\ProdutoRequest;
use App\Models\Produtos\Produto;
use App\Repositories\Produtos\ProdutoRepository;
use Illuminate\Validation\ValidationException;

/** "cadastrar produto Coca lata a 6,50 estoque 10", "novo serviço troca de tela 150". */
abstract class CadastrarItem extends Rotina
{
    public function __construct(private readonly ProdutoRepository $produtos) {}

    abstract public function chave(): string;

    abstract public function titulo(): string;

    abstract public function atalho(): string;

    abstract public function exemplos(): array;

    abstract public function palavras(): array;

    abstract public function padrao(): string;

    /** "produto" ou "serviço", do jeito que aparece na frase. */
    abstract protected function substantivo(): string;

    /** Palavra sem acento usada no reconhecimento ("produto", "servico"). */
    abstract protected function palavraChave(): string;

    /** "1" produto ou "2" serviço. */
    abstract protected function tipo(): string;

    abstract protected function controlaEstoque(): bool;

    abstract protected function perguntaPreco(): string;

    public function permissao(): string
    {
        return 'produtos';
    }

    protected function conectores(): array
    {
        return ['preco' => 'a', 'estoque' => 'estoque'];
    }

    /** "cadastra a troca de tela como serviço, 150" */
    public function reconhecerNatural(string $texto): ?string
    {
        $grupos = Leitor::grupos(
            '/^(?:cadastr|registr|salv|adicion|inclu|coloc|bot)\w*\s+(?:(?:o|a)\s+)?(?<nome>.+?)\s+como\s+(?:(?:um|uma|nov[oa])\s+)?'
            .$this->palavraChave().'\b(?<mais>.*)$/u',
            $texto,
        );

        return $grupos === null ? null : trim($grupos['nome'].' '.$grupos['mais']);
    }

    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if ($campo === 'preco' && ! $this->respostaDePreco($resposta)) {
            return $argumento;
        }
        if ($campo === 'estoque' && ! $this->respostaDeEstoque($resposta)) {
            return $argumento;
        }
        if ($campo === 'estoque' && $this->respostaSem($resposta, 'estoque')) {
            return Leitor::limpar($argumento.' '.$resposta);
        }

        return parent::completar($argumento, $campo, $resposta, $termo);
    }

    public function preparar(string $argumento): Resposta
    {
        $leitor = new Leitor($argumento);
        $codigo = $leitor->extrair('/\b(?:codigo|cod|sku)\s+(?<codigo>[A-Za-z0-9._-]{1,50})\b/u')['codigo'] ?? null;
        $semPreco = $leitor->extrair('/\bsem\s+(?:preco|valor)\b/u') !== null;
        $semEstoque = $leitor->extrair('/\bsem\s+estoque\b/u') !== null;

        $estoque = null;
        if ($this->controlaEstoque() && ($grupos = $leitor->extrair('/\b(?:estoque|qtd|quantidade)\s+(?<qtd>\d+(?:[.,]\d{1,3})?)\b/u'))) {
            $estoque = Leitor::numero($grupos['qtd']);
        }

        $preco = $semPreco ? 0.0 : $leitor->valor();
        $nome = $this->nomeLimpo($leitor->resto());

        if ($nome === '') {
            return $this->perguntar('Qual o nome do '.$this->substantivo().'?', 'nome');
        }
        if (mb_strlen($nome) < 2) {
            return $this->perguntar('Qual o nome completo do '.$this->substantivo().'?', 'nome', termo: $nome);
        }
        if ($preco === null) {
            return $this->perguntar($this->perguntaPreco(), 'preco', [Resposta::opcao('Sem preço', 'sem preço')]);
        }
        if ($this->controlaEstoque() && ! $semEstoque && $estoque === null) {
            return $this->perguntar('Qual o estoque inicial?', 'estoque', [Resposta::opcao('Sem estoque', 'sem estoque')]);
        }

        $dados = [
            'empresa_id' => $this->empresaId(),
            'nome' => $nome,
            'tipo' => $this->tipo(),
            'preco_venda' => number_format($preco, 2, '.', ''),
            'controla_estoque' => $this->controlaEstoque(),
            'estoque_atual' => number_format($this->controlaEstoque() ? (float) $estoque : 0, 3, '.', ''),
            'ativo' => true,
        ];
        if ($codigo !== null && $codigo !== '') {
            $dados['codigo'] = $codigo;
        }

        try {
            FormularioInterno::validar(ProdutoRequest::class, $dados);
        } catch (ValidationException $e) {
            if ($this->faltaCampo($e, 'nome')) {
                return $this->perguntar('Qual o nome do '.$this->substantivo().'?', 'nome');
            }

            return $this->erroDeValidacao($e);
        }

        $repetido = Produto::query()
            ->where('empresa_id', $this->empresaId())
            ->where('tipo', $this->tipo())
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])
            ->exists();

        $detalhes = [$this->controlaEstoque()
            ? ((float) $estoque > 0 ? 'Estoque inicial: '.self::quantidade((float) $estoque) : 'Sem estoque inicial.')
            : 'Serviço, sem controle de estoque.'];

        return Resposta::confirmacao(
            $this->chave(),
            'Cadastrar o '.$this->substantivo().' '.$nome.' por '.self::moeda($preco).'?',
            $dados,
            $detalhes,
            avisos: $repetido ? ['Já existe um '.$this->substantivo().' com esse nome.'] : [],
        );
    }

    public function executar(array $dados): Resposta
    {
        try {
            $formulario = FormularioInterno::validar(ProdutoRequest::class, $dados);
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        $produto = $this->produtos->store($formulario->validated());

        return Resposta::sucesso(
            'Pronto! '.ucfirst($this->substantivo()).' cadastrado: '.$produto->nome.'.',
            ['rotulo' => 'Completar cadastro', 'url' => route('produto.edit', $produto)],
            registro: ['entidade' => 'produto', 'id' => $produto->id],
        );
    }

    private function respostaDePreco(string $resposta): bool
    {
        if (preg_match('/\bsem\s+(?:preco|valor)\b/u', Leitor::normalizar($resposta))) {
            return true;
        }

        return (new Leitor($resposta))->valor() !== null;
    }

    private function respostaDeEstoque(string $resposta): bool
    {
        if ($this->respostaSem($resposta, 'estoque')) {
            return true;
        }

        return (bool) preg_match('/\d+(?:[.,]\d{1,3})?/u', Leitor::normalizar($resposta));
    }

    private function nomeLimpo(string $resto): string
    {
        $limpo = preg_replace('/\s+(?:por|pre[çc]o(?:\s+de\s+venda)?|valor|a)$/iu', '', trim($resto)) ?? trim($resto);

        return mb_substr(Leitor::aparar($limpo), 0, 255);
    }
}
