<?php

namespace App\Comandos;

use App\Models\Clientes\Cliente;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Base das rotinas: conversa (perguntas e respostas), formatação e mensagens comuns. */
abstract class Rotina implements Comando
{
    /** Resposta que dispensa o dado perguntado ("sem cliente", "nenhum"). */
    private const SEM = '/^(?:sem|nenhum|nenhuma|nao tem|nao sei|deixa sem|pula|pular)\b/u';

    private const UNIDADES = 'x|un|und|unid|unidades?|kg|g|l|lt|pc|pcs|pecas?|cx|caixas?|pct|pacotes?';

    /** Rótulo no começo de um trecho corrigido ("cpf 123", "vence 31/02"): fica na frase, só o valor muda. */
    private const ROTULO = '/^(?:cpf|cnpj|documento|doc|telefone|tel|fone|celular|cel|whatsapp|whats|zap|(?:com\s+)?(?:vencimento|vencendo|vence|venc\.?)(?:\s+(?:em|no dia|dia|para|na|no))?)\s*:?\s+/u';

    public function reconhecerNatural(string $texto): ?string
    {
        return null;
    }

    /**
     * Com termo, a resposta troca aquele trecho da frase (o nome ambíguo, a data errada);
     * "sem ..." tira o trecho. Sem termo, a resposta entra no fim com o conector do campo ("vence amanhã").
     */
    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        $resposta = Leitor::limpar($resposta);

        if ($termo !== null && $termo !== '') {
            if (preg_match(self::SEM, Leitor::normalizar($resposta))) {
                return $this->semTermo($argumento, $termo);
            }

            if (preg_match(self::ROTULO, Leitor::normalizar($termo), $rotulo) && ! preg_match(self::ROTULO, Leitor::normalizar($resposta).' ')) {
                $resposta = mb_substr($termo, 0, mb_strlen($rotulo[0])).$resposta;
            }

            $trocado = preg_replace_callback(
                '/(?<![\p{L}\p{N}#])'.preg_quote($termo, '/').'(?![\p{L}\p{N}])/iu',
                fn () => $resposta,
                $argumento,
                1,
                $trocas,
            );
            if ($trocas > 0) {
                return Leitor::limpar((string) $trocado);
            }
        }

        return Leitor::limpar($argumento.' '.($this->conectores()[$campo] ?? '').' '.$resposta);
    }

    /** @return array<string, string> palavra que liga cada resposta à frase ("vence" antes da data) */
    protected function conectores(): array
    {
        return [];
    }

    protected function usuario(): User
    {
        return auth()->user();
    }

    protected function empresaId(): int
    {
        return (int) $this->usuario()->empresa_id;
    }

    protected function localizador(): Localizador
    {
        return new Localizador($this->empresaId());
    }

    protected static function moeda(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected static function data(CarbonInterface $data): string
    {
        return $data->format('d/m/Y');
    }

    protected static function quantidade(float $quantidade): string
    {
        return rtrim(rtrim(number_format($quantidade, 3, ',', '.'), '0'), ',');
    }

    /** @param list<string|array{rotulo: string, enviar: string}> $opcoes */
    protected function perguntar(string $texto, string $campo, array $opcoes = [], ?string $termo = null): Resposta
    {
        return Resposta::pergunta($texto, $campo, $opcoes, $termo);
    }

    /** "sem cliente", "sem estoque": a pessoa dispensou o campo, não informou um nome. */
    protected function respostaSem(string $resposta, string $oque): bool
    {
        return (bool) preg_match('/\bsem\s+'.$oque.'\b/u', Leitor::normalizar($resposta));
    }

    /** O formulário recusou porque o campo obrigatório veio vazio: o chat pergunta, em vez de só mostrar o erro. */
    protected function faltaCampo(ValidationException $excecao, string $campo): bool
    {
        $mensagens = $excecao->errors()[$campo] ?? null;
        if (! is_array($mensagens)) {
            return false;
        }

        $texto = mb_strtolower(implode(' ', $mensagens));

        return str_contains($texto, 'obrigat') || str_contains($texto, 'pelo menos');
    }

    /** Erros do formulário da tela, no máximo dois, numa frase só. */
    protected function erroDeValidacao(ValidationException $excecao): Resposta
    {
        $mensagens = array_values(array_unique(Arr::flatten($excecao->errors())));

        return Resposta::erro(implode(' ', array_slice($mensagens, 0, 2)));
    }

    /** Dado digitado errado (data, CPF): pergunta o certo e troca só aquele trecho. */
    protected function corrigirEntrada(EntradaInvalida $excecao, string $pergunta, array $opcoes = []): Resposta
    {
        return $excecao->trecho === null
            ? Resposta::erro($excecao->getMessage())
            : $this->perguntar($excecao->getMessage().' '.$pergunta, 'corrigir', $opcoes, $excecao->trecho);
    }

    /**
     * Quando a busca não achou exatamente um: pergunta qual (vários) ou sugere nomes parecidos (nenhum).
     * Null quando achou.
     *
     * @param  string  $oque  "o cliente", "o produto"...
     * @param  list<array{rotulo: string, enviar: string}>  $extras  botões a mais ("Sem cliente", "Cadastrar")
     */
    protected function problemaNaBusca(Achado $achado, string $oque, string $campo, array $extras = []): ?Resposta
    {
        if ($achado->encontrou()) {
            return null;
        }

        $termo = $achado->termo;

        if (! $achado->nenhum()) {
            return $this->perguntar('Achei mais de um “'.$termo.'”. Qual deles?', $campo, $this->opcoesDe($achado->opcoes), $termo);
        }

        if ($achado->sugestoes->isNotEmpty()) {
            return $this->perguntar(
                'Não encontrei '.$oque.' “'.$termo.'”. Seria algum destes?',
                $campo,
                [...$this->opcoesDe($achado->sugestoes), ...$extras],
                $termo,
            );
        }

        return $this->perguntar('Não encontrei '.$oque.' “'.$termo.'”. Qual é o nome certo?', $campo, $extras, $termo);
    }

    /**
     * Cada registro vira um botão que envia "#id" (o nome pode se repetir; o id não).
     *
     * @param  Collection<int, Model>  $registros
     * @return list<array{rotulo: string, enviar: string}>
     */
    protected function opcoesDe(Collection $registros): array
    {
        return $registros->map(fn (Model $r) => Resposta::opcao($this->rotuloDaOpcao($r), '#'.$r->getKey()))->values()->all();
    }

    protected function rotuloDaOpcao(Model $registro): string
    {
        return match (true) {
            $registro instanceof Produto => $registro->nome.' · '.self::moeda((float) $registro->preco_venda),
            $registro instanceof Cliente && filled($registro->cidade) => $registro->nome.' · '.$registro->cidade,
            default => (string) $registro->nome,
        };
    }

    /**
     * "cliente Maria" é explícito; "para Maria" só conta se Maria for cliente ("suporte para TV" é produto).
     * Com $aceitaDe, "notebook da Maria" também vale quando Maria está cadastrada.
     *
     * @return array{0: ?Cliente, 1: ?Resposta}
     */
    protected function clienteInformado(Leitor $leitor, bool $aceitaDe = false): array
    {
        $soltos = 'para|pra|pro'.($aceitaDe ? '|da|do' : '');
        $nome = $leitor->rotulado('do cliente|da cliente|para o cliente|para a cliente|cliente')
            ?? $leitor->rotulado($soltos, fn (string $nome) => ! $this->localizador()->cliente($nome)->nenhum());

        if ($nome === null) {
            return [null, null];
        }

        $achado = $this->localizador()->cliente($nome);
        $extras = [Resposta::opcao('Sem cliente', 'sem cliente')];
        if ($this->usuario()->pode('clientes.balcao')) {
            $extras[] = Resposta::opcao('Cadastrar “'.$nome.'”', 'cadastrar cliente '.$nome);
        }

        return [$achado->registro, $this->problemaNaBusca($achado, 'o cliente', 'cliente', $extras)];
    }

    /**
     * Forma citada na frase. Não tira o nome: quem for usar chama FormaNaFrase::remover.
     *
     * @return array{0: ?FormaPagamento, 1: ?Resposta}
     */
    protected function formaInformada(Leitor $leitor): array
    {
        $achado = $this->localizador()->formaPagamento($leitor->resto());

        if ($achado->nenhum()) {
            return [null, null];
        }
        if (! $achado->encontrou()) {
            return [null, $this->perguntar('Qual forma de pagamento?', 'forma', $achado->opcoes->pluck('nome')->all())];
        }

        return [$achado->registro, null];
    }

    /**
     * "2 coca lata, 1 salgado", "coca x3", "3 un parafuso e 2 porca", "000012".
     *
     * @return array{0: list<array{produto: Produto, quantidade: float, preco: float}>, 1: ?Resposta}
     */
    protected function itensInformados(string $texto, ?string $perguntaSeVazio, string $acao = 'vender'): array
    {
        // Vírgula entre dígitos é decimal ("1,5 kg"), não separa itens.
        $partes = array_filter(array_map('trim', preg_split('/\s*[;+]\s*|\s*,(?!\d)\s*|(?<!\d)\s*,\s*|\s+e\s+(?=\d)|\s+mais\s+/u', $texto) ?: []));
        if ($partes === []) {
            return [[], $perguntaSeVazio === null ? null : $this->perguntar($perguntaSeVazio, 'itens')];
        }

        $itens = [];
        foreach ($partes as $parte) {
            [$quantidade, $termo] = $this->quantidadeETermo($parte);
            if ($quantidade <= 0) {
                return [[], Resposta::erro('A quantidade de “'.$termo.'” precisa ser maior que zero.')];
            }

            $achado = $this->localizador()->produto($termo);
            if ($problema = $this->problemaNaBusca($achado, 'o produto', 'produto')) {
                return [[], $problema];
            }

            /** @var Produto $produto */
            $produto = $achado->registro;
            if ((float) $produto->preco_venda <= 0) {
                return [[], Resposta::erro('O produto “'.$produto->nome.'” está sem preço de venda. Ajuste o cadastro antes de '.$acao.'.')];
            }

            $chave = $produto->getKey();
            $itens[$chave] ??= ['produto' => $produto, 'quantidade' => 0.0, 'preco' => (float) $produto->preco_venda];
            $itens[$chave]['quantidade'] += $quantidade;
        }

        return [array_values($itens), null];
    }

    /** "2 coca" ou "coca x2" é item; "notebook" e "8mm parafuso" não. */
    protected function pareceItem(string $parte): bool
    {
        $normal = Leitor::normalizar($parte);

        return (bool) preg_match('/^\d+(?:[.,]\d{1,3})?(?:\s*(?:x|un|und|unid|kg|g|l|pc|cx))?\s+\S/u', $normal)
            || (bool) preg_match('/\bx\s*\d+(?:[.,]\d{1,3})?$/u', $normal);
    }

    /** @param list<array{produto: Produto, quantidade: float, preco: float}> $itens */
    protected function itensParaConfirmacao(array $itens): array
    {
        return array_map(fn (array $item) => [
            'descricao' => self::quantidade($item['quantidade']).' × '.$item['produto']->nome,
            'valor' => self::moeda(round($item['quantidade'] * $item['preco'], 2)),
        ], $itens);
    }

    /** @param list<array{produto: Produto, quantidade: float, preco: float}> $itens */
    protected function itensParaGravar(array $itens): array
    {
        return array_map(fn (array $item) => [
            'produto_id' => $item['produto']->getKey(),
            'quantidade' => (string) $item['quantidade'],
            'preco_unitario' => number_format($item['preco'], 2, '.', ''),
            'desconto' => '0',
        ], $itens);
    }

    protected function totalDosItens(array $itens): float
    {
        return array_sum(array_map(fn (array $item) => round($item['quantidade'] * $item['preco'], 2), $itens));
    }

    /** @return array{0: float, 1: string} */
    private function quantidadeETermo(string $parte): array
    {
        if (preg_match('/^(?<qtd>\d+(?:[.,]\d{1,3})?)\s*(?:(?:'.self::UNIDADES.')\b\.?)?\s+(?<termo>.+)$/iu', $parte, $m)) {
            return [Leitor::numero($m['qtd']), Leitor::aparar($m['termo'])];
        }
        if (preg_match('/^(?<termo>.+?)\s*x\s*(?<qtd>\d+(?:[.,]\d{1,3})?)$/iu', $parte, $m)) {
            return [Leitor::numero($m['qtd']), Leitor::aparar($m['termo'])];
        }

        return [1.0, Leitor::aparar($parte)];
    }

    /** Tira o termo e o rótulo antes dele ("do cliente Mria", "para Mria", "cpf 123"). */
    private function semTermo(string $argumento, string $termo): string
    {
        $leitor = new Leitor($argumento);
        $leitor->extrair(
            '/(?:\b(?:do|da|de|ao|a|o|para|pra|pro)\s+)?(?:\b(?:cliente|fornecedor)\s+)?(?<![\p{L}\p{N}#])'
            .preg_quote(Leitor::normalizar($termo), '/').'(?![\p{L}\p{N}])/u'
        );

        return $leitor->resto();
    }
}
