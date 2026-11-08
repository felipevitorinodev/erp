<?php

namespace App\Comandos\Rotinas;

use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Http\Requests\Vendas\VendaRequest;
use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Produtos\Produto;
use App\Services\Vendas\VendaService;
use Illuminate\Validation\ValidationException;

/**
 * "a Maria comprou 2 coca e 1 salgado no pix", "vendi 3 parafuso no dinheiro", "nova venda".
 *
 * Só à vista: venda a prazo precisa das parcelas, que são definidas na tela de venda.
 * Usa o preço de venda do cadastro e passa pelas mesmas regras da tela (estoque, preço mínimo, comissão).
 * Não emite NFC-e.
 */
final class RegistrarVenda extends Rotina
{
    private const UNIDADES = 'x|un|und|unid|unidades?|kg|g|l|lt|pc|pcs|pecas?|cx|caixas?|pct|pacotes?';

    /** "ele comprou", "um cliente levou": não é nome de cliente. */
    private const SEM_NOME = ['ele', 'ela', 'eles', 'elas', 'alguem', 'cliente', 'um cliente', 'uma cliente', 'consumidor', 'consumidor final', 'a gente', 'gente', 'eu', 'nos'];

    private const FORMAS_NA_PERGUNTA = 5;

    public function __construct(private readonly VendaService $vendas) {}

    public function chave(): string
    {
        return 'venda';
    }

    public function permissao(): string
    {
        return 'vendas';
    }

    public function titulo(): string
    {
        return 'Nova venda';
    }

    public function atalho(): string
    {
        return 'nova venda';
    }

    public function exemplos(): array
    {
        return [
            'a Maria comprou 2 coca no pix',
            'vendi 3 parafuso e 1 martelo no dinheiro',
        ];
    }

    public function palavras(): array
    {
        return ['venda', 'vender', 'vendi', 'vende', 'comprou', 'levou', 'pegou', 'cupom'];
    }

    public function padrao(): string
    {
        return '/^(?:(?:faz|lanc|registr|cri)\w*\s+(?:(?:uma|a|nova)\s+)?)?(?:nova\s+)?vend(?:a|er|e|i)\b/u';
    }

    /** "a Maria comprou 2 coca no pix" → "2 coca no pix, cliente Maria". */
    public function reconhecerNatural(string $texto): ?string
    {
        $grupos = Leitor::grupos('/^(?:(?:o|a)\s+)?(?:cliente\s+)?(?<cliente>[^\d,;]+?)\s+(?:comprou|levou|pegou)\s+(?<resto>.+)$/u', $texto);
        if ($grupos === null) {
            return null;
        }

        $cliente = Leitor::aparar($grupos['cliente']);

        return in_array(Leitor::normalizar($cliente), self::SEM_NOME, true)
            ? $grupos['resto']
            : $grupos['resto'].', cliente '.$cliente;
    }

    protected function conectores(): array
    {
        return ['itens' => ',', 'forma' => 'no'];
    }

    /** A forma de pagamento só entra se existir; senão a pergunta volta. */
    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if ($campo === 'forma' && $this->localizador()->formaPagamento($resposta)->nenhum()) {
            return $argumento;
        }

        return parent::completar($argumento, $campo, $resposta, $termo);
    }

    public function preparar(string $argumento): Resposta
    {
        $leitor = new Leitor($argumento);

        [$forma, $problemaForma] = $this->formaDePagamento($leitor);
        if ($problemaForma?->tipo === 'info') {
            return $problemaForma; // venda a prazo: vai para a tela de venda
        }

        [$cliente, $problemaCliente] = $this->cliente($leitor);

        $resto = $leitor->resto();
        if ($resto === '') {
            return $this->perguntar('O que foi vendido?', 'itens');
        }

        [$itens, $problemaItens] = $this->itens($resto);
        if ($problemaItens !== null) {
            return $problemaItens;
        }
        if ($problemaCliente !== null) {
            return $problemaCliente;
        }
        if ($problemaForma !== null) {
            return $problemaForma;
        }
        if ($forma === null) {
            return $this->perguntar('Como foi pago?', 'forma', $this->formasAVista());
        }

        $total = array_sum(array_map(fn (array $item) => round($item['quantidade'] * $item['preco'], 2), $itens));

        $dados = [
            'cliente_id' => $cliente?->getKey(),
            'forma_pagamento' => $forma->nome,
            'situacao' => 'confirmada',
            'itens' => array_map(fn (array $item) => [
                'produto_id' => $item['produto']->getKey(),
                'quantidade' => (string) $item['quantidade'],
                'preco_unitario' => number_format($item['preco'], 2, '.', ''),
                'desconto' => '0',
            ], $itens),
        ];

        try {
            FormularioInterno::validar(VendaRequest::class, $dados + ['data_venda' => today()->toDateString()]);
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        return Resposta::confirmacao(
            $this->chave(),
            'Registrar venda de '.self::moeda($total).' no '.$forma->nome.($cliente ? ' para '.$cliente->nome : '').'?',
            $dados,
            itens: array_map(fn (array $item) => [
                'descricao' => self::quantidade($item['quantidade']).' × '.$item['produto']->nome,
                'valor' => self::moeda(round($item['quantidade'] * $item['preco'], 2)),
            ], $itens),
            avisos: $this->avisos($itens),
        );
    }

    public function executar(array $dados): Resposta
    {
        $dados['data_venda'] = today()->toDateString();

        try {
            $resultado = $this->vendas->gravar(FormularioInterno::validar(VendaRequest::class, $dados));
        } catch (ValidationException $e) {
            return $this->erroDeValidacao($e);
        }

        $venda = $resultado['venda'];

        return Resposta::sucesso(
            'Pronto! Venda nº '.$venda->numero.' registrada.',
            ['rotulo' => 'Ver a venda', 'url' => route('venda.show', $venda)],
            [self::moeda((float) $venda->total).' no '.$venda->forma_pagamento],
            array_values(array_filter([
                $resultado['aviso_estoque'],
                $resultado['situacao'] !== 'confirmada' ? 'A venda ficou em andamento. Confira na tela de vendas.' : null,
            ])),
            ['entidade' => 'venda', 'id' => (int) $venda->getKey()],
        );
    }

    /**
     * A forma é procurada pelo nome cadastrado e tirada da frase junto com "no", "via", "pago no"...
     *
     * @return array{0: ?FormaPagamento, 1: ?Resposta}
     */
    private function formaDePagamento(Leitor $leitor): array
    {
        $achado = $this->localizador()->formaPagamento($leitor->resto());

        if ($achado->nenhum()) {
            return [null, null];
        }
        if (! $achado->encontrou()) {
            return [null, $this->perguntar('Qual forma de pagamento?', 'forma', $achado->opcoes->pluck('nome')->all())];
        }

        /** @var FormaPagamento $forma */
        $forma = $achado->registro;
        if (! $forma->isAvista()) {
            return [null, Resposta::info(
                'Venda a prazo precisa das parcelas, então é feita na tela de venda.',
                link: ['rotulo' => 'Abrir nova venda', 'url' => route('venda.create')],
            )];
        }

        $this->tirarDaFrase($leitor, $forma);

        return [$forma, null];
    }

    /**
     * Com conector ("no cartão") sai toda menção, inclusive a de uma resposta anterior;
     * sem conector, só a primeira. Assim "cartão presente no cartão" mantém o produto.
     */
    private function tirarDaFrase(Leitor $leitor, FormaPagamento $forma): void
    {
        $nome = Leitor::normalizar($forma->nome);
        $termos = array_values(array_filter(
            array_unique([$nome, ...(preg_split('/[\s\/-]+/', $nome) ?: [])]),
            fn (string $termo) => strlen($termo) >= 3,
        ));

        $removeu = false;
        foreach ($termos as $termo) {
            $comConector = '/(?:\b(?:pago|paga|pagamento)\s+(?:(?:no|na|em|via|com)\s+)?|\b(?:no|na|em|via|com)\s+)(?:cartao\s+(?:de\s+)?)?'.preg_quote($termo, '/').'\b/u';
            while ($leitor->extrair($comConector) !== null) {
                $removeu = true;
            }
        }

        if ($removeu) {
            return;
        }

        foreach ($termos as $termo) {
            if ($leitor->extrair('/\b(?:cartao\s+(?:de\s+)?)?'.preg_quote($termo, '/').'\b/u') !== null) {
                return;
            }
        }
    }

    /**
     * "cliente Maria" é explícito; "para Maria" só conta se Maria for cliente ("suporte para TV" é produto).
     *
     * @return array{0: ?Cliente, 1: ?Resposta}
     */
    private function cliente(Leitor $leitor): array
    {
        $nome = $leitor->rotulado('para o cliente|para a cliente|cliente')
            ?? $leitor->rotulado('para|pra|pro', fn (string $nome) => ! $this->localizador()->cliente($nome)->nenhum());

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
     * "2 coca lata, 1 salgado", "coca x3", "3 un parafuso e 2 porca", "000012".
     *
     * @return array{0: list<array{produto: Produto, quantidade: float, preco: float}>, 1: ?Resposta}
     */
    private function itens(string $texto): array
    {
        // Vírgula entre dígitos é decimal ("1,5 kg"), não separa itens.
        $partes = array_filter(array_map('trim', preg_split('/\s*[;+]\s*|\s*,(?!\d)\s*|(?<!\d)\s*,\s*|\s+e\s+(?=\d)|\s+mais\s+/u', $texto) ?: []));
        if ($partes === []) {
            return [[], $this->perguntar('O que foi vendido?', 'itens')];
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
                return [[], Resposta::erro('O produto “'.$produto->nome.'” está sem preço de venda. Ajuste o cadastro antes de vender.')];
            }

            $chave = $produto->getKey();
            $itens[$chave] ??= ['produto' => $produto, 'quantidade' => 0.0, 'preco' => (float) $produto->preco_venda];
            $itens[$chave]['quantidade'] += $quantidade;
        }

        return [array_values($itens), null];
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

    /** @return list<string> */
    private function formasAVista(): array
    {
        return FormaPagamento::query()
            ->where('empresa_id', $this->empresaId())
            ->where('ativo', true)
            ->orderBy('nome')
            ->get()
            ->filter(fn (FormaPagamento $f) => $f->isAvista())
            ->take(self::FORMAS_NA_PERGUNTA)
            ->pluck('nome')
            ->values()
            ->all();
    }

    /** @param list<array{produto: Produto, quantidade: float, preco: float}> $itens */
    private function avisos(array $itens): array
    {
        $avisos = [];
        foreach ($itens as $item) {
            $produto = $item['produto'];
            if ($produto->controla_estoque && (float) $produto->estoque_atual < $item['quantidade']) {
                $avisos[] = $produto->nome.' tem só '.self::quantidade((float) $produto->estoque_atual).' em estoque.';
            }
        }

        if (Empresa::query()->whereKey($this->empresaId())->value('nfce_emitir_pdv')) {
            $avisos[] = 'Sem NFC-e: para emitir a nota, use o PDV.';
        }

        return $avisos;
    }
}
