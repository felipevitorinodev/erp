<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\FormaNaFrase;
use App\Comandos\FormularioInterno;
use App\Comandos\Leitor;
use App\Comandos\PeriodoFalado;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Http\Requests\Vendas\VendaRequest;
use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Produtos\Produto;
use App\Services\Vendas\VendaService;
use Carbon\CarbonImmutable;
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
        return ['itens' => ',', 'forma' => 'no', 'data' => 'data'];
    }

    /** A forma de pagamento só entra se existir; senão a pergunta volta. */
    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if ($campo === 'forma' && $this->localizador()->formaPagamento($resposta)->nenhum()) {
            return $argumento;
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
            $dataDita = PeriodoFalado::extrairRotulo($leitor, 'data(?:\s+da\s+venda)?', CarbonImmutable::today())
                ?? $leitor->data(CarbonImmutable::today());
        } catch (EntradaInvalida $e) {
            return $this->corrigirEntrada($e, 'Qual é a data certa?', ['Hoje', 'Ontem']);
        }

        [$forma, $problemaForma] = $this->formaDePagamento($leitor);
        if ($problemaForma?->tipo === 'info') {
            return $problemaForma; // venda a prazo: vai para a tela de venda
        }

        [$cliente, $problemaCliente] = $this->clienteInformado($leitor);

        $resto = $leitor->resto();
        if ($resto === '') {
            return $this->perguntar('O que foi vendido?', 'itens');
        }

        [$itens, $problemaItens] = $this->itensInformados($resto, 'O que foi vendido?');
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
        if ($dataDita === null) {
            return $this->perguntar('Qual a data da venda?', 'data', ['Hoje', 'Ontem']);
        }

        $total = $this->totalDosItens($itens);

        $dados = [
            'cliente_id' => $cliente?->getKey(),
            'data_venda' => $dataDita->toDateString(),
            'data_informada' => true,
            'forma_pagamento' => $forma->nome,
            'situacao' => 'confirmada',
            'itens' => $this->itensParaGravar($itens),
        ];

        try {
            FormularioInterno::validar(VendaRequest::class, $this->semMarcador($dados));
        } catch (ValidationException $e) {
            if ($this->faltaCampo($e, 'itens')) {
                return $this->perguntar('O que foi vendido?', 'itens');
            }
            if ($this->faltaCampo($e, 'data_venda')) {
                return $this->perguntar('Qual a data da venda?', 'data', ['Hoje', 'Ontem']);
            }

            return $this->erroDeValidacao($e);
        }

        return Resposta::confirmacao(
            $this->chave(),
            'Registrar venda de '.self::moeda($total).' no '.$forma->nome.($cliente ? ' para '.$cliente->nome : '').'?',
            $dados,
            ['Data: '.self::data($dataDita), 'Situação: confirmada'],
            itens: $this->itensParaConfirmacao($itens),
            avisos: $this->avisos($itens),
        );
    }

    public function executar(array $dados): Resposta
    {
        if (empty($dados['data_informada'])) {
            $dados['data_venda'] = today()->toDateString();
        }

        try {
            $resultado = $this->vendas->gravar(FormularioInterno::validar(VendaRequest::class, $this->semMarcador($dados)));
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
        [$forma, $problema] = $this->formaInformada($leitor);
        if ($problema !== null || $forma === null) {
            return [$forma, $problema];
        }

        if (! $forma->isAvista()) {
            return [null, Resposta::info(
                'Venda a prazo precisa das parcelas, então é feita na tela de venda.',
                link: ['rotulo' => 'Abrir nova venda', 'url' => route('venda.create')],
            )];
        }

        FormaNaFrase::remover($leitor, $forma);

        return [$forma, null];
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
