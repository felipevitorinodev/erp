<?php

namespace App\Comandos\Rotinas;

use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Models\Produtos\Produto;

/** "estoque coca", "quanto custa a coca?", "tem pão no estoque?": consulta, não grava nada. */
final class ConsultarProduto extends Rotina
{
    private const LIMITE = 5;

    /** Jeitos naturais de perguntar; o grupo "termo" é o produto. */
    private const PERGUNTAS = [
        '/^quanto\s+(?:custa|custam|ta|esta|e|sai|fica|vale)\s+(?:(?:o|a|os|as|um|uma)\s+)?(?<termo>.+)$/u',
        '/^(?:qual\s+(?:e\s+)?(?:o\s+)?)?valor\s+(?:d[oa]s?|de)\s+(?<termo>.+)$/u',
        '/^quant[oa]s?\s+(?<termo>.+?)\s+(?:tem|temos|tenho|ha|sobrou|sobraram|resta|restam)(?:\s+(?:no|em)\s+estoque)?$/u',
        '/^(?:ainda\s+)?(?:tem|temos|tenho|ha)\s+(?<termo>.+?)(?:\s+(?:no|em)\s+estoque)?$/u',
        '/^(?<termo>.+?)\s+(?:tem|ta|esta)\s+(?:no\s+|em\s+)?estoque$/u',
    ];

    public function chave(): string
    {
        return 'produto';
    }

    public function permissao(): string
    {
        return 'vendas';
    }

    public function titulo(): string
    {
        return 'Preço e estoque';
    }

    public function atalho(): string
    {
        return 'consultar produto';
    }

    public function exemplos(): array
    {
        return ['quanto custa a coca?', 'tem pão no estoque?'];
    }

    public function palavras(): array
    {
        return ['estoque', 'preco', 'precos', 'produto', 'produtos', 'custa', 'consultar', 'quanto', 'valor'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:consult|ver|mostr|busc|procur)\w*\s+(?:o\s+|a\s+)?|qual\s+(?:e\s+)?(?:o\s+)?)?(?:estoque|precos?|produtos?)\b(?:\s+(?:do|da|dos|das|de)\b)?/u';
    }

    public function reconhecerNatural(string $texto): ?string
    {
        foreach (self::PERGUNTAS as $regex) {
            if ($grupos = Leitor::grupos($regex, $texto)) {
                return $grupos['termo'];
            }
        }

        return null;
    }

    public function preparar(string $argumento): Resposta
    {
        $termo = Leitor::aparar(trim($argumento, " \t?.!"));
        if ($termo === '') {
            return $this->perguntar('Qual produto?', 'termo');
        }

        $produtos = $this->localizador()->produtos($termo, self::LIMITE + 1);

        if ($produtos->isEmpty()) {
            $parecidos = $this->localizador()->produtosParecidos($termo);

            return $parecidos->isEmpty()
                ? Resposta::erro('Não encontrei nenhum produto com “'.$termo.'”.')
                : $this->perguntar('Não encontrei “'.$termo.'”. Seria algum destes?', 'termo', $this->opcoesDe($parecidos), $termo);
        }

        if ($produtos->count() === 1) {
            /** @var Produto $produto */
            $produto = $produtos->first();

            return Resposta::info(
                $produto->nome.': '.self::moeda((float) $produto->preco_venda),
                [ucfirst($this->estoque($produto))],
                opcoes: [Resposta::opcao('Vender 1', 'vender 1 #'.$produto->getKey())],
            );
        }

        return Resposta::info(
            'Achei estes produtos com “'.$termo.'”:',
            $produtos->count() > self::LIMITE ? ['Mostrando os '.self::LIMITE.' primeiros. Escreva mais do nome para filtrar.'] : [],
            $produtos->take(self::LIMITE)->map(fn (Produto $p) => [
                'descricao' => $p->nome,
                'valor' => self::moeda((float) $p->preco_venda).' · '.$this->estoque($p),
            ])->values()->all(),
            link: $this->usuario()->pode('produtos') ? ['rotulo' => 'Abrir produtos', 'url' => route('produto.index', ['busca' => $termo])] : null,
        );
    }

    public function executar(array $dados): Resposta
    {
        return $this->preparar((string) ($dados['termo'] ?? ''));
    }

    private function estoque(Produto $produto): string
    {
        if (! $produto->controla_estoque) {
            return 'sem controle de estoque';
        }

        $quantidade = (float) $produto->estoque_atual;
        if ($quantidade <= 0) {
            return 'sem estoque';
        }

        $texto = self::quantidade($quantidade).' em estoque';

        return $quantidade <= (float) $produto->estoque_minimo ? $texto.' (baixo)' : $texto;
    }
}
