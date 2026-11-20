<?php

namespace App\Comandos\Rotinas;

use App\Comandos\EntradaInvalida;
use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Models\Clientes\Cliente;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Funcionarios\Funcionario;
use App\Models\Orcamentos\Orcamento;
use App\Models\OrdensServico\OrdemServico;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Repositories\Clientes\ClienteRepository;
use App\Repositories\ContasPagar\ContaPagarRepository;
use App\Repositories\ContasReceber\ContaReceberRepository;
use App\Repositories\Fornecedores\FornecedorRepository;
use App\Repositories\Orcamentos\OrcamentoRepository;
use App\Repositories\OrdensServico\OrdemServicoRepository;
use App\Repositories\Produtos\ProdutoRepository;
use App\Repositories\Vendas\VendaRepository;
use App\Services\Vendas\VendaService;
use App\Support\Auditoria;
use App\Support\MensagemPublica;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Consulta, edita por conversa ou exclui com confirmação os registros das rotinas do assistente. */
final class GerenciarRegistro extends Rotina
{
    private const PREFIXO_EDICAO = '__edicao_registro__:';

    private const ENTIDADE = '/^(?<entidade>contas?\s+a\s+receber|contas?\s+receber|contas?\s+a\s+pagar|contas?\s+pagar|(?:ordem|ordens)\s+(?:de\s+)?servi[cç]os?|o\.?s\.?|orcamentos?|vendas?|clientes?|fornecedor(?:es)?|produtos?|servi[cç]os?)(?![\p{L}\p{N}])\s*(?<alvo>.*)$/u';

    /** Artigo antes do tipo ("editar o cliente"). "os 15" é a ordem de serviço 15, não o artigo. */
    private const ARTIGO = '(?:(?:o|a|as|um|uma|os(?!\s+(?:#|n[º°o]?\s*)?\d+\s*$))\s+)?';

    public function __construct(
        private readonly ClienteRepository $clientes,
        private readonly FornecedorRepository $fornecedores,
        private readonly ProdutoRepository $produtos,
        private readonly VendaRepository $vendas,
        private readonly OrcamentoRepository $orcamentos,
        private readonly OrdemServicoRepository $ordens,
        private readonly ContaReceberRepository $contasReceber,
        private readonly ContaPagarRepository $contasPagar,
        private readonly VendaService $vendaService,
    ) {}

    public function chave(): string
    {
        return 'gerenciar_registro';
    }

    public function permissao(): string
    {
        return 'comandos';
    }

    public function titulo(): string
    {
        return 'Consultar, editar ou excluir registros';
    }

    public function atalho(): string
    {
        return 'consultar cliente';
    }

    public function exemplos(): array
    {
        return [
            'consultar cliente Maria Souza',
            'editar produto Coca lata',
            'excluir conta a receber 15',
        ];
    }

    public function palavras(): array
    {
        return [
            'consultar', 'ver', 'mostrar', 'buscar', 'procurar', 'pesquisar', 'listar', 'detalhar', 'visualizar',
            'editar', 'alterar', 'atualizar', 'modificar', 'mudar',
            'excluir', 'apagar', 'deletar', 'remover',
            'cliente', 'fornecedor', 'produto', 'servico', 'venda', 'orcamento', 'ordem', 'conta',
        ];
    }

    /** A intenção é analisada em preparar(), mantendo a frase inteira como argumento. */
    public function padrao(): string
    {
        return '/^(?=(?:consult\w*|ver|mostr\w*|busc\w*|procur\w*|pesquis\w*|list\w*|detalh\w*|visualiz\w*|edit\w*|alter\w*|atualiz\w*|modific\w*|mud\w*|exclu\w*|apag\w*|delet\w*|remov\w*)\s+)/u';
    }

    public function reconhecerNatural(string $texto): ?string
    {
        return null;
    }

    /** O nome do campo e o novo valor são texto livre: "preço de venda" não é uma consulta de produto. */
    public function respostaLivre(string $campo): bool
    {
        return in_array($campo, ['campo_edicao', 'valor_edicao'], true);
    }

    public function preparar(string $argumento): Resposta
    {
        if (str_starts_with($argumento, self::PREFIXO_EDICAO)) {
            $estado = $this->decodificarEdicao($argumento);

            return $estado === null
                ? Resposta::erro('A edição expirou ou não pôde ser recuperada. Comece novamente.')
                : $this->prepararEdicao($estado);
        }

        $pedido = Leitor::grupos(
            '/^(?<acao>consult\w*|ver|mostr\w*|busc\w*|procur\w*|pesquis\w*|list\w*|detalh\w*|visualiz\w*|edit\w*|alter\w*|atualiz\w*|modific\w*|mud\w*|exclu\w*|apag\w*|delet\w*|remov\w*)\s+'.self::ARTIGO.'(?<resto>.+)$/u',
            $argumento,
        );
        if ($pedido === null || ($entidade = Leitor::grupos(self::ENTIDADE, $pedido['resto'])) === null) {
            return Resposta::erro('Diga o tipo e o registro, por exemplo: “editar produto Coca lata” ou “excluir cliente Maria”.');
        }

        $acao = self::acao($pedido['acao']);
        $tipo = self::tipo($entidade['entidade']);
        if ($tipo === null) {
            return Resposta::erro('Esse tipo de registro não está disponível no assistente.');
        }
        if (! $this->usuario()->pode(self::permissaoDa($tipo))) {
            return Resposta::erro('Seu perfil não tem acesso a esse módulo.');
        }

        $alvo = trim($entidade['alvo']);
        if ($alvo === '') {
            return $this->perguntar('Qual o nome ou número do registro?', 'alvo');
        }

        $registros = $this->localizar($tipo, $alvo);
        if ($registros->isEmpty()) {
            return Resposta::erro('Não encontrei esse registro.');
        }
        if ($registros->count() > 1) {
            return $this->perguntar(
                'Encontrei mais de um registro. Qual deles?',
                'alvo',
                $registros->map(fn (Model $registro) => Resposta::opcao(
                    $this->rotulo($tipo, $registro),
                    '#'.$registro->getKey(),
                ))->all(),
                $alvo,
            );
        }

        $registro = $registros->first();

        // A mesma trava da tela vale antes da prévia: não se pede confirmação do que não pode ser feito.
        if ($acao === 'excluir' && ($erro = $this->erroAoExcluir($tipo, $registro)) !== null) {
            return Resposta::info($erro, link: $this->link($tipo, $registro, false));
        }

        return match ($acao) {
            'consultar' => Resposta::info($this->resumo($tipo, $registro), link: $this->link($tipo, $registro, false)),
            'editar' => $this->iniciarEdicao($tipo, $registro),
            'excluir' => Resposta::confirmacao(
                $this->chave(),
                'Excluir '.$this->rotulo($tipo, $registro).'? Essa ação não poderá ser desfeita.',
                ['acao' => 'excluir', 'entidade' => $tipo, 'id' => (int) $registro->getKey()],
                $this->detalhesExclusao($tipo, $registro),
            ),
        };
    }

    public function executar(array $dados): Resposta
    {
        $tipo = (string) ($dados['entidade'] ?? '');
        $id = (int) ($dados['id'] ?? 0);
        if ($id < 1 || ! in_array($tipo, ['cliente', 'fornecedor', 'produto', 'servico', 'venda', 'orcamento', 'ordem_servico', 'conta_receber', 'conta_pagar'], true)) {
            return Resposta::erro('O registro não pôde ser identificado. Faça a busca novamente.');
        }
        if (! $this->usuario()->pode(self::permissaoDa($tipo))) {
            return Resposta::erro('Seu perfil não tem mais acesso a esse módulo.');
        }

        $registro = $this->localizarPorId($tipo, $id);
        if ($registro === null) {
            return Resposta::erro('Esse registro não existe mais ou não pertence à sua empresa.');
        }

        $acao = $dados['acao'] ?? 'excluir';
        if ($acao === 'editar') {
            return $this->executarEdicao($tipo, $registro, $dados);
        }
        if ($acao !== 'excluir') {
            return Resposta::erro('A ação solicitada não é válida.');
        }

        if ($erro = $this->erroAoExcluir($tipo, $registro)) {
            return Resposta::info($erro, link: ['rotulo' => 'Abrir registro', 'url' => $this->urlDetalhe($tipo, $registro)]);
        }

        $origensVenda = $tipo === 'venda' ? $this->vendaService->origensDaVenda($registro) : null;

        try {
            match ($tipo) {
                'cliente' => $this->clientes->destroy($id),
                'fornecedor' => $this->fornecedores->destroy($id),
                'produto', 'servico' => $this->produtos->destroy($id),
                'venda' => $this->vendas->destroy($id),
                'orcamento' => $this->orcamentos->destroy($id),
                'ordem_servico' => $this->ordens->destroy($id),
                'conta_receber' => $this->contasReceber->destroy($id),
                'conta_pagar' => $this->contasPagar->destroy($id),
            };
        } catch (\Exception $excecao) {
            // Os repositórios recusam com exceção (venda confirmada, orçamento aprovado...), como nas telas.
            return Resposta::erro(MensagemPublica::de($excecao));
        }

        if ($tipo === 'produto' || $tipo === 'servico') {
            Auditoria::registrar('produto.excluido', 'produto', $id, [
                'codigo' => $registro->codigo,
                'estoque_atual' => (float) $registro->estoque_atual,
            ]);
        }

        $avisoVenda = $origensVenda !== null ? $this->vendaService->avisoOrigensReabertas($origensVenda, $registro) : '';

        if ($tipo === 'venda') {
            Auditoria::registrar('venda.excluida', 'venda', $id, [
                'numero' => $registro->numero,
                'situacao' => $registro->situacao,
                'total' => (float) $registro->total,
            ]);
        } elseif ($tipo === 'conta_receber') {
            Auditoria::registrar('conta_receber.excluida', 'conta_receber', $id, [
                'valor' => (float) $registro->valor,
                'situacao' => $registro->situacao,
            ]);
        } elseif ($tipo === 'conta_pagar') {
            Auditoria::registrar('conta_pagar.excluida', 'conta_pagar', $id, [
                'valor' => (float) $registro->valor,
                'situacao' => $registro->situacao,
            ]);
        }

        return Resposta::sucesso(
            ucfirst($this->nomeEntidade($tipo)).($this->feminino($tipo) ? ' excluída.' : ' excluído.').$avisoVenda,
            registro: ['entidade' => $tipo, 'id' => $id],
        );
    }

    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        if ($campo === 'campo_edicao') {
            $pedido = Leitor::grupos(
                '/^(?<acao>edit\w*|alter\w*|atualiz\w*|modific\w*|mud\w*)\s+'.self::ARTIGO.'(?<resto>.+)$/u',
                $argumento,
            );
            $entidade = $pedido === null ? null : Leitor::grupos(self::ENTIDADE, $pedido['resto']);
            $tipo = $entidade === null ? null : self::tipo($entidade['entidade']);
            $registros = $tipo === null ? collect() : $this->localizar($tipo, trim($entidade['alvo']));
            $registro = $registros->count() === 1 ? $registros->first() : null;
            $campoSelecionado = $registro === null ? null : $this->resolverCampo($tipo, $registro, $resposta);

            if ($registro !== null && $campoSelecionado === null && self::campoDeOutraRotina($tipo, $resposta) !== null) {
                return $this->codificarEdicao([
                    'fase' => 'outra_rotina',
                    'entidade' => $tipo,
                    'id' => (int) $registro->getKey(),
                    'campo' => Leitor::normalizar($resposta),
                ]);
            }
            if ($registro === null || $campoSelecionado === null) {
                return $argumento;
            }

            return $this->codificarEdicao([
                'fase' => 'valor',
                'entidade' => $tipo,
                'id' => (int) $registro->getKey(),
                'campo' => $campoSelecionado,
            ]);
        }

        if ($campo === 'valor_edicao') {
            $estado = $this->decodificarEdicao($argumento);
            if ($estado === null || ! in_array($estado['fase'] ?? null, ['valor', 'previa'], true)) {
                return $argumento;
            }
            $estado['fase'] = 'previa';
            $estado['valor'] = Leitor::limpar($resposta);

            return $this->codificarEdicao($estado);
        }

        return parent::completar($argumento, $campo, $resposta, $termo);
    }

    private function iniciarEdicao(string $tipo, Model $registro): Resposta
    {
        if (! $this->edicaoDisponivel($tipo, $registro)) {
            return Resposta::info(
                'Este registro não pode mais ser editado; confira sua situação.',
                link: $this->link($tipo, $registro, true),
            );
        }

        $campos = $this->camposEditaveis($tipo, $registro);
        if ($campos === []) {
            return Resposta::info('Não há campos editáveis disponíveis para este registro.');
        }

        return $this->perguntar(
            'Qual informação deseja alterar em '.$this->rotulo($tipo, $registro).'? Você também pode escrever o nome do campo.',
            'campo_edicao',
            array_map(
                fn (string $campo, array $spec) => Resposta::opcao($spec['rotulo'], $campo),
                array_keys($campos),
                array_values($campos),
            ),
        );
    }

    private function perguntarValorEdicao(
        string $tipo,
        Model $registro,
        string $campo,
        array $spec,
        ?string $erro = null,
    ): Resposta {
        $opcoes = [];
        if ($spec['tipo'] === 'booleano') {
            $opcoes = [
                Resposta::opcao('Ativo', 'ativo'),
                Resposta::opcao('Inativo', 'inativo'),
            ];
        } elseif ($spec['tipo'] === 'cliente' || $spec['tipo'] === 'fornecedor') {
            $opcoes[] = Resposta::opcao('Sem vínculo', 'limpar');
        }
        if ($spec['nullable'] && $spec['tipo'] !== 'booleano' && $spec['tipo'] !== 'cliente' && $spec['tipo'] !== 'fornecedor') {
            $opcoes[] = Resposta::opcao('Limpar campo', 'limpar');
        }

        $atual = $this->valorFormatado($registro, $campo, $registro->getRawOriginal($campo));
        $texto = ($erro !== null ? $erro.' ' : '')
            .'Informe o novo valor para '.$spec['rotulo'].' de '.$this->rotulo($tipo, $registro)
            .' (atual: '.$atual.').';

        return Resposta::pergunta($texto, 'valor_edicao', $opcoes);
    }

    /** @return array<string, array{rotulo: string, tipo: string, nullable: bool}> */
    private function camposEditaveis(string $tipo, Model $registro): array
    {
        $texto = fn (string $rotulo, bool $nullable = true) => ['rotulo' => $rotulo, 'tipo' => 'texto', 'nullable' => $nullable];
        $data = fn (string $rotulo, bool $nullable = true) => ['rotulo' => $rotulo, 'tipo' => 'data', 'nullable' => $nullable];
        $camposContato = [
            'nome' => $texto('Nome', false),
            'nome_fantasia' => $texto('Nome fantasia'),
            'cpf' => ['rotulo' => 'CPF', 'tipo' => 'cpf', 'nullable' => true],
            'cnpj' => ['rotulo' => 'CNPJ', 'tipo' => 'cnpj', 'nullable' => true],
            'inscricao_estadual' => $texto('Inscrição estadual'),
            'inscricao_municipal' => $texto('Inscrição municipal'),
            'email' => ['rotulo' => 'Email', 'tipo' => 'email', 'nullable' => true],
            'telefone' => $texto('Telefone'),
            'celular' => $texto('Celular'),
            'cep' => $texto('CEP'),
            'logradouro' => $texto('Logradouro'),
            'numero' => $texto('Número'),
            'complemento' => $texto('Complemento'),
            'bairro' => $texto('Bairro'),
            'cidade' => $texto('Cidade'),
            'estado' => ['rotulo' => 'Estado (UF)', 'tipo' => 'uf', 'nullable' => true],
            'observacoes' => $texto('Observações'),
            'ativo' => ['rotulo' => 'Situação (ativo/inativo)', 'tipo' => 'booleano', 'nullable' => false],
        ];

        return match ($tipo) {
            'cliente', 'fornecedor' => $camposContato,
            'produto', 'servico' => [
                'nome' => $texto('Nome', false),
                'descricao' => $texto('Descrição'),
                'codigo_barras' => $texto('Código de barras'),
                'referencia' => $texto('Referência'),
                'preco_custo' => ['rotulo' => 'Preço de custo', 'tipo' => 'numero', 'nullable' => true],
                'preco_venda' => ['rotulo' => 'Preço de venda', 'tipo' => 'numero', 'nullable' => true],
                'preco_minimo' => ['rotulo' => 'Preço mínimo', 'tipo' => 'numero', 'nullable' => true],
                'estoque_minimo' => ['rotulo' => 'Estoque mínimo', 'tipo' => 'numero', 'nullable' => true],
                'estoque_maximo' => ['rotulo' => 'Estoque máximo', 'tipo' => 'numero', 'nullable' => true],
                'controla_estoque' => ['rotulo' => 'Controle de estoque', 'tipo' => 'booleano', 'nullable' => false],
                'ativo' => ['rotulo' => 'Situação (ativo/inativo)', 'tipo' => 'booleano', 'nullable' => false],
            ],
            'venda' => [
                'cliente_id' => ['rotulo' => 'Cliente', 'tipo' => 'cliente', 'nullable' => true],
                'data_venda' => $data('Data da venda', false),
                'data_saida' => $data('Data de saída'),
                'data_entrega' => $data('Data de entrega'),
                'observacoes' => $texto('Observações'),
            ],
            'orcamento' => [
                'cliente_id' => ['rotulo' => 'Cliente', 'tipo' => 'cliente', 'nullable' => true],
                'data_orcamento' => $data('Data do orçamento', false),
                'data_entrega' => $data('Data de entrega'),
                'forma_pagamento' => ['rotulo' => 'Forma de pagamento', 'tipo' => 'texto', 'nullable' => true],
                'observacoes' => $texto('Observações'),
            ],
            'ordem_servico' => [
                'cliente_id' => ['rotulo' => 'Cliente', 'tipo' => 'cliente', 'nullable' => true],
                'funcionario_id' => ['rotulo' => 'Técnico', 'tipo' => 'tecnico', 'nullable' => true],
                'data_abertura' => $data('Data de abertura', false),
                'data_previsao' => $data('Data de previsão'),
                'equipamento' => $texto('Equipamento', false),
                'marca' => $texto('Marca'),
                'modelo' => $texto('Modelo'),
                'numero_serie' => $texto('Número de série'),
                'acessorios' => $texto('Acessórios'),
                'defeito_relatado' => $texto('Defeito relatado'),
                'diagnostico' => $texto('Diagnóstico'),
                'servico_executado' => $texto('Serviço executado'),
                'garantia' => $texto('Garantia'),
                'forma_pagamento' => $texto('Forma de pagamento'),
                'observacoes' => $texto('Observações'),
            ],
            'conta_receber' => [
                'descricao' => $texto('Descrição', false),
                ...($registro->venda_id ? [] : ['cliente_id' => ['rotulo' => 'Cliente', 'tipo' => 'cliente', 'nullable' => true]]),
                'valor' => ['rotulo' => 'Valor', 'tipo' => 'dinheiro', 'nullable' => false],
                'data_vencimento' => $data('Data de vencimento', false),
                'forma_pagamento' => $texto('Forma de pagamento'),
                'observacoes' => $texto('Observações'),
            ],
            'conta_pagar' => [
                'descricao' => $texto('Descrição', false),
                ...($registro->entrada_estoque_id ? [] : ['fornecedor_id' => ['rotulo' => 'Fornecedor', 'tipo' => 'fornecedor', 'nullable' => true]]),
                'valor' => ['rotulo' => 'Valor', 'tipo' => 'dinheiro', 'nullable' => false],
                'data_vencimento' => $data('Data de vencimento', false),
                'forma_pagamento' => $texto('Forma de pagamento'),
                'observacoes' => $texto('Observações'),
            ],
            default => [],
        };
    }

    private function resolverCampo(string $tipo, Model $registro, string $resposta): ?string
    {
        $normal = Leitor::normalizar($resposta);
        $normal = preg_replace('/^(?:(?:o|a)\s+)?(?:campo\s+)?(?:alterar|editar|mudar)\s+/u', '', $normal) ?? $normal;
        $chave = preg_replace('/[^\p{L}\p{N}]+/u', '', $normal) ?? $normal;
        $aliases = [
            'endereco' => 'logradouro',
            'logradouro' => 'logradouro',
            'preco' => 'preco_venda',
            'precovenda' => 'preco_venda',
            'precodevenda' => 'preco_venda',
            'precocusto' => 'preco_custo',
            'precodecusto' => 'preco_custo',
            'datadavenda' => 'data_venda',
            'dataemissao' => 'data_venda',
            'datadoorcamento' => 'data_orcamento',
            'datadeabertura' => 'data_abertura',
            'datadeprevisao' => 'data_previsao',
            'datadevencimento' => 'data_vencimento',
            'datadeentrega' => 'data_entrega',
            'datadesaida' => 'data_saida',
            'controledeestoque' => 'controla_estoque',
            'controlaestoque' => 'controla_estoque',
            'numerodeserie' => 'numero_serie',
            'previsao' => 'data_previsao',
            'vencimento' => 'data_vencimento',
            'situacao' => 'ativo',
        ];
        $campo = $aliases[$chave] ?? $chave;
        $campos = $this->camposEditaveis($tipo, $registro);

        // O apelido já devolve a chave do campo ("preco_venda"), que não passa pela comparação sem sublinhado abaixo.
        if (isset($campos[$campo])) {
            return $campo;
        }

        foreach ($campos as $nome => $spec) {
            $nomeNormal = preg_replace('/[^\p{L}\p{N}]+/u', '', Leitor::normalizar($nome)) ?? '';
            $rotulo = preg_replace('/[^\p{L}\p{N}]+/u', '', Leitor::normalizar($spec['rotulo'])) ?? '';
            if ($campo === $nomeNormal || $campo === $rotulo) {
                return $nome;
            }
        }

        return null;
    }

    /**
     * Campos que a tela também não deixa digitar: mudam por outra rotina, que grava o histórico.
     * Devolve a explicação, ou null quando a resposta não fala de um deles.
     */
    private static function campoDeOutraRotina(string $tipo, string $resposta): ?string
    {
        $chave = preg_replace('/[^\p{L}\p{N}]+/u', '', Leitor::normalizar($resposta)) ?? '';

        return match (true) {
            in_array($tipo, ['produto', 'servico'], true) && in_array($chave, ['estoque', 'estoqueatual', 'saldo', 'saldodeestoque', 'quantidade', 'quantidadeemestoque'], true) => 'O estoque atual não é digitado: ele muda pela entrada de estoque, pelas vendas e pelo ajuste de estoque, que guardam o histórico.',
            in_array($tipo, ['venda', 'orcamento', 'ordem_servico'], true) && in_array($chave, ['itens', 'item', 'produtos', 'produto', 'servicos', 'total', 'valor', 'valortotal', 'subtotal', 'desconto', 'acrescimo'], true) => 'Itens, descontos e total são alterados na tela, onde o valor é recalculado. Abra o registro para mudar.',
            in_array($tipo, ['conta_receber', 'conta_pagar'], true) && in_array($chave, ['valorpago', 'valorrecebido', 'pagamento', 'recebimento', 'baixa'], true) => 'Pagamentos e recebimentos são lançados pela baixa da conta, na tela. Abra o registro para baixar.',
            default => null,
        };
    }

    private function prepararEdicao(array $estado): Resposta
    {
        $tipo = (string) ($estado['entidade'] ?? '');
        $id = (int) ($estado['id'] ?? 0);
        $campo = (string) ($estado['campo'] ?? '');
        if ($id < 1 || ! in_array($tipo, ['cliente', 'fornecedor', 'produto', 'servico', 'venda', 'orcamento', 'ordem_servico', 'conta_receber', 'conta_pagar'], true)) {
            return Resposta::erro('A edição expirou ou não pôde ser recuperada. Comece novamente.');
        }
        if (! $this->usuario()->pode(self::permissaoDa($tipo))) {
            return Resposta::erro('Seu perfil não tem mais acesso a esse módulo.');
        }

        $registro = $this->localizarPorId($tipo, $id);
        if ($registro === null || ! $this->edicaoDisponivel($tipo, $registro)) {
            return Resposta::erro('Este registro não existe mais ou não pode ser editado na situação atual.');
        }

        if (($estado['fase'] ?? null) === 'outra_rotina') {
            return Resposta::info(
                self::campoDeOutraRotina($tipo, $campo) ?? 'Esse campo não pode ser alterado pelo assistente.',
                // A baixa da conta fica na tela de detalhes; o resto, na edição.
                link: $this->link($tipo, $registro, ! in_array($tipo, ['conta_receber', 'conta_pagar'], true)),
            );
        }

        $campos = $this->camposEditaveis($tipo, $registro);
        if (! isset($campos[$campo])) {
            return Resposta::erro('Esse campo não pode ser alterado pelo assistente.');
        }

        if (($estado['fase'] ?? null) === 'valor') {
            return $this->perguntarValorEdicao($tipo, $registro, $campo, $campos[$campo]);
        }
        if (($estado['fase'] ?? null) !== 'previa') {
            return Resposta::erro('Não consegui continuar esta edição. Comece novamente.');
        }

        [$valor, $erro] = $this->validarValorEdicao($tipo, $registro, $campo, $estado['valor'] ?? null);
        if ($erro !== null) {
            return $this->perguntarValorEdicao($tipo, $registro, $campo, $campos[$campo], $erro);
        }

        $anterior = $registro->getRawOriginal($campo);
        if ($this->valoresIguais($anterior, $valor)) {
            return Resposta::info('O novo valor é igual ao atual. Nenhuma alteração foi feita.');
        }

        $detalhes = [
            'Atual: '.$this->valorFormatado($registro, $campo, $anterior),
            'Novo: '.$this->valorFormatado($registro, $campo, $valor),
        ];
        if ($campo === 'valor'
            && in_array($tipo, ['conta_receber', 'conta_pagar'], true)
            && (float) ($registro->valor_pago ?? 0) > 0
            && (float) $valor <= (float) $registro->valor_pago) {
            $detalhes[] = 'A conta ficará quitada pelo valor já pago ou recebido.';
        }

        return Resposta::confirmacao(
            $this->chave(),
            'Alterar '.$campos[$campo]['rotulo'].' de '.$this->rotulo($tipo, $registro).'?',
            [
                'acao' => 'editar',
                'entidade' => $tipo,
                'id' => $id,
                'campo' => $campo,
                'anterior' => $anterior,
                'valor' => $valor,
            ],
            $detalhes,
        );
    }

    private function validarValorEdicao(string $tipo, Model $registro, string $campo, mixed $entrada): array
    {
        $spec = $this->camposEditaveis($tipo, $registro)[$campo] ?? null;
        if ($spec === null) {
            return [null, 'Esse campo não pode ser alterado pelo assistente.'];
        }

        $entrada = is_string($entrada) ? trim($entrada) : $entrada;
        if ($entrada === '') {
            $entrada = null;
        }
        $normalEntrada = is_string($entrada) ? Leitor::normalizar($entrada) : '';
        if (is_null($entrada) || in_array($normalEntrada, ['limpar', 'sem', 'nenhum', 'nenhuma', 'sem cliente', 'sem fornecedor'], true)) {
            // Na tela, fornecedor pessoa física exige CPF e jurídica exige CNPJ: o único documento não pode ser apagado.
            if ($tipo === 'fornecedor' && in_array($spec['tipo'], ['cpf', 'cnpj'], true)
                && filled($registro->{$campo}) && blank($spec['tipo'] === 'cpf' ? $registro->cnpj : $registro->cpf)) {
                return [null, 'O fornecedor precisa ficar com CPF ou CNPJ. Informe o novo número em vez de limpar.'];
            }

            return $spec['nullable']
                ? [null, null]
                : [null, 'Esse campo é obrigatório e não pode ser limpo.'];
        }

        $valor = $entrada;
        if ($spec['tipo'] === 'booleano') {
            $normal = Leitor::normalizar((string) $entrada);
            $valor = is_bool($entrada) ? $entrada : match ($normal) {
                'ativo', 'ativa', 'sim', 'true', '1' => true,
                'inativo', 'inativa', 'nao', 'false', '0' => false,
                default => null,
            };
            if ($valor === null) {
                return [null, 'Responda ativo ou inativo.'];
            }
        } elseif ($spec['tipo'] === 'data') {
            if (preg_match('/^(?<ano>\d{4})-(?<mes>\d{2})-(?<dia>\d{2})$/u', (string) $entrada, $partes)) {
                if (! checkdate((int) $partes['mes'], (int) $partes['dia'], (int) $partes['ano'])) {
                    return [null, 'A data informada não existe no calendário.'];
                }
                $valor = $entrada;
            } else {
                try {
                    $data = (new Leitor('data '.(string) $entrada))->data(CarbonImmutable::today());
                } catch (EntradaInvalida $excecao) {
                    return [null, $excecao->getMessage()];
                }
                $valor = $data?->toDateString() ?? $entrada;
            }
        } elseif ($spec['tipo'] === 'uf' && is_string($entrada)) {
            $valor = mb_strtoupper($entrada);
        } elseif (in_array($spec['tipo'], ['numero', 'dinheiro'], true) && is_string($entrada)) {
            $valor = self::normalizarNumero($entrada);
        } elseif (in_array($spec['tipo'], ['cliente', 'fornecedor', 'tecnico'], true)) {
            [$valor, $erro] = $this->resolverRelacionamento($spec['tipo'], (string) $entrada);
            if ($erro !== null) {
                return [null, $erro];
            }
        } elseif (is_string($entrada)) {
            $valor = $entrada;
        }

        $regras = match ($spec['tipo']) {
            'email' => ['nullable', 'email', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14', 'cpf'],
            'cnpj' => ['nullable', 'string', 'max:18', 'cnpj'],
            'uf' => ['nullable', 'string', 'size:2'],
            'booleano' => ['required', 'boolean'],
            'data' => $spec['nullable'] ? ['nullable', 'date'] : ['required', 'date'],
            'numero' => ['nullable', 'numeric', 'min:0'],
            'dinheiro' => ['required', 'numeric', 'min:0.01'],
            'cliente', 'fornecedor', 'tecnico' => ['nullable', 'integer'],
            default => $spec['nullable']
                ? ['nullable', 'string', 'max:'.self::limiteTexto($tipo, $campo)]
                : ['required', 'string', 'max:'.self::limiteTexto($tipo, $campo)],
        };

        if ($spec['tipo'] === 'cpf' && $tipo === 'cliente') {
            $regras[] = Rule::unique('clientes', 'cpf')
                ->where('empresa_id', $this->empresaId())
                ->whereNull('deleted_at')
                ->ignore($registro->getKey());
        } elseif ($spec['tipo'] === 'cnpj' && $tipo === 'cliente') {
            $regras[] = Rule::unique('clientes', 'cnpj')
                ->where('empresa_id', $this->empresaId())
                ->whereNull('deleted_at')
                ->ignore($registro->getKey());
        }

        $validacao = Validator::make([$campo => $valor], [$campo => $regras], [], [$campo => mb_strtolower($spec['rotulo'])]);
        if ($validacao->fails()) {
            return [null, $validacao->errors()->first()];
        }

        if ($campo === 'preco_minimo') {
            $precoVenda = $tipo === 'produto' || $tipo === 'servico'
                ? (float) $registro->preco_venda
                : 0;
            if ($valor !== null && $precoVenda > 0 && (float) $valor > $precoVenda) {
                return [null, 'O preço mínimo não pode ser maior que o preço de venda.'];
            }
        }
        if ($campo === 'preco_venda' && $registro->preco_minimo !== null && (float) $valor < (float) $registro->preco_minimo) {
            return [null, 'O preço de venda não pode ficar abaixo do preço mínimo cadastrado.'];
        }
        if ($campo === 'valor' && (float) $valor < (float) ($registro->valor_pago ?? 0)) {
            return [null, 'O valor da conta não pode ser menor que o valor já pago ou recebido.'];
        }
        if ($campo === 'data_venda' && $registro->data_saida && CarbonImmutable::parse($valor)->gt(CarbonImmutable::parse($registro->data_saida))) {
            return [null, 'A data da venda não pode ficar depois da data de saída já informada.'];
        }
        if ($campo === 'data_saida' && $valor !== null && CarbonImmutable::parse($valor)->lt(CarbonImmutable::parse($registro->data_venda))) {
            return [null, 'A data de saída não pode ser anterior à data da venda.'];
        }
        if ($campo === 'data_abertura' && $registro->data_previsao && CarbonImmutable::parse($registro->data_previsao)->lt(CarbonImmutable::parse($valor))) {
            return [null, 'A data de abertura não pode ficar depois da previsão já informada.'];
        }
        if ($campo === 'data_previsao' && $valor !== null && CarbonImmutable::parse($valor)->lt(CarbonImmutable::parse($registro->data_abertura))) {
            return [null, 'A previsão não pode ser anterior à abertura.'];
        }

        return [$valor, null];
    }

    private function resolverRelacionamento(string $tipo, string $alvo): array
    {
        $classe = match ($tipo) {
            'cliente' => Cliente::class,
            'fornecedor' => Fornecedor::class,
            'tecnico' => Funcionario::class,
        };
        $consulta = $classe::query()->where('empresa_id', $this->empresaId());
        $id = self::numeroDoAlvo($alvo);
        if ($id !== null) {
            $consulta->whereKey($id);
        } else {
            $consulta->where('nome', 'like', '%'.addcslashes(trim($alvo), '%_\\').'%');
        }

        $registros = $consulta->limit(2)->get();
        if ($registros->count() !== 1) {
            return [null, $registros->isEmpty()
                ? 'Não encontrei esse cadastro nesta empresa. Informe outro nome ou o número do registro.'
                : 'Encontrei mais de um cadastro com esse nome. Informe o número, por exemplo, #15.'];
        }

        return [(int) $registros->sole()->getKey(), null];
    }

    private function executarEdicao(string $tipo, Model $registro, array $dados): Resposta
    {
        $campo = (string) ($dados['campo'] ?? '');
        $campos = $this->camposEditaveis($tipo, $registro);
        if (! isset($campos[$campo]) || ! $this->edicaoDisponivel($tipo, $registro)) {
            return Resposta::erro('Este campo ou registro não pode mais ser editado.');
        }
        if (! $this->valoresIguais($registro->getRawOriginal($campo), $dados['anterior'] ?? null)) {
            return Resposta::erro('O registro mudou desde a prévia. Consulte-o novamente antes de editar.');
        }

        [$valor, $erro] = $this->validarValorEdicao($tipo, $registro, $campo, $dados['valor'] ?? null);
        if ($erro !== null) {
            return Resposta::erro($erro);
        }

        $precos = ['preco_custo', 'preco_venda', 'preco_minimo'];
        $precosAntes = in_array($campo, $precos, true) ? array_map('floatval', $registro->only($precos)) : null;

        try {
            $this->atualizarCampo($tipo, $registro, $campo, $valor);
        } catch (\Exception $excecao) {
            return Resposta::erro(MensagemPublica::de($excecao));
        }

        $registro->refresh();

        // Mesma trilha da tela de produto: mudança de preço fica registrada com antes e depois.
        if ($precosAntes !== null) {
            Auditoria::registrar('produto.preco_alterado', 'produto', (int) $registro->getKey(), [
                'antes' => $precosAntes,
                'depois' => array_map('floatval', $registro->only($precos)),
            ]);
        }

        return Resposta::sucesso(
            ucfirst($this->nomeEntidade($tipo)).($this->feminino($tipo) ? ' atualizada: ' : ' atualizado: ').$this->rotulo($tipo, $registro).'.',
            link: $this->link($tipo, $registro, false),
            registro: ['entidade' => $tipo, 'id' => (int) $registro->getKey()],
        );
    }

    private function atualizarCampo(string $tipo, Model $registro, string $campo, mixed $valor): void
    {
        $dados = [$campo => $valor];

        match ($tipo) {
            'cliente' => $this->clientes->update($registro, $dados),
            'fornecedor' => $this->fornecedores->update($registro, $dados),
            'produto', 'servico' => $this->produtos->update($registro, $dados),
            'venda' => $this->vendas->update($registro, $dados, $this->itensAtuais($registro, 'venda_id')),
            'orcamento' => $this->orcamentos->update($registro, $dados, $this->itensAtuais($registro, 'orcamento_id')),
            'ordem_servico' => $this->ordens->update($registro, $dados, $this->itensAtuais($registro, 'ordem_servico_id')),
            'conta_receber' => $this->contasReceber->update($registro, $dados),
            'conta_pagar' => $this->contasPagar->update($registro, $dados),
        };
    }

    private function itensAtuais(Model $registro, string $chavePai): array
    {
        $itens = $registro->itens()->get();
        if ($itens->isEmpty() && in_array($chavePai, ['venda_id', 'orcamento_id'], true)) {
            throw new \RuntimeException('Inclua ao menos um item antes de editar este registro.');
        }

        return $itens->map(static function (Model $item) use ($chavePai): array {
            $dados = array_intersect_key($item->getAttributes(), array_fill_keys($item->getFillable(), true));
            unset($dados['id'], $dados[$chavePai], $dados['created_at'], $dados['updated_at']);

            return $dados;
        })->all();
    }

    private function valorFormatado(Model $registro, string $campo, mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return 'não informado';
        }
        if (in_array($campo, ['ativo', 'controla_estoque'], true)) {
            return $valor ? 'ativo' : 'inativo';
        }
        if (in_array($campo, ['cliente_id', 'fornecedor_id', 'funcionario_id'], true)) {
            $relacao = match ($campo) {
                'cliente_id' => 'cliente',
                'fornecedor_id' => 'fornecedor',
                'funcionario_id' => 'tecnico',
            };
            $classe = match ($relacao) {
                'cliente' => Cliente::class,
                'fornecedor' => Fornecedor::class,
                'tecnico' => Funcionario::class,
            };
            $nome = $classe::query()->where('empresa_id', $this->empresaId())->whereKey($valor)->value('nome');

            return $nome ?: 'não informado';
        }
        if (in_array($campo, ['data_venda', 'data_saida', 'data_entrega', 'data_orcamento', 'data_abertura', 'data_previsao', 'data_vencimento'], true)) {
            return CarbonImmutable::parse($valor)->format('d/m/Y');
        }
        if (in_array($campo, ['preco_custo', 'preco_venda', 'preco_minimo', 'valor'], true)) {
            return self::moeda((float) $valor);
        }

        return (string) $valor;
    }

    private function codificarEdicao(array $estado): string
    {
        return self::PREFIXO_EDICAO.base64_encode(json_encode($estado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function decodificarEdicao(string $argumento): ?array
    {
        if (! str_starts_with($argumento, self::PREFIXO_EDICAO)) {
            return null;
        }
        $json = base64_decode(substr($argumento, strlen(self::PREFIXO_EDICAO)), true);
        if ($json === false) {
            return null;
        }
        $estado = json_decode($json, true);

        return is_array($estado) ? $estado : null;
    }

    private function valoresIguais(mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        return (string) $a === (string) $b;
    }

    private static function normalizarNumero(string $valor): string
    {
        $valor = trim(str_replace(' ', '', (string) preg_replace('/^R\$/iu', '', trim($valor))));
        if (str_contains($valor, ',') && str_contains($valor, '.')) {
            return str_replace(',', '.', str_replace('.', '', $valor));
        }

        return str_replace(',', '.', $valor);
    }

    private static function limiteTexto(string $tipo, string $campo): int
    {
        return match ($campo) {
            'nome', 'nome_fantasia', 'logradouro', 'complemento', 'bairro' => 255,
            'inscricao_estadual', 'inscricao_municipal' => 20,
            'email' => 255,
            'telefone', 'celular' => 20,
            'cep' => 9,
            'cidade' => 100,
            'estado' => 2,
            'codigo_barras', 'referencia' => 50,
            'descricao' => in_array($tipo, ['produto', 'servico'], true) ? 10000 : 200,
            'numero' => 20,
            'equipamento' => 150,
            'marca', 'modelo', 'numero_serie' => 80,
            'acessorios' => 255,
            'defeito_relatado', 'diagnostico', 'servico_executado', 'observacoes' => 5000,
            'garantia' => 100,
            'forma_pagamento' => 100,
            default => 255,
        };
    }

    private static function acao(string $verbo): string
    {
        $verbo = Leitor::normalizar($verbo);

        return match (true) {
            preg_match('/^(?:consult|ver|mostr|busc|procur|pesquis|list|detalh|visualiz)/u', $verbo) === 1 => 'consultar',
            preg_match('/^(?:edit|alter|atualiz|modific|mud)/u', $verbo) === 1 => 'editar',
            default => 'excluir',
        };
    }

    private static function tipo(string $entidade): ?string
    {
        $entidade = Leitor::normalizar($entidade);

        return match (true) {
            preg_match('/^clientes?$/u', $entidade) === 1 => 'cliente',
            preg_match('/^fornecedor(?:es)?$/u', $entidade) === 1 => 'fornecedor',
            preg_match('/^produtos?$/u', $entidade) === 1 => 'produto',
            preg_match('/^servicos?$/u', $entidade) === 1 => 'servico',
            preg_match('/^vendas?$/u', $entidade) === 1 => 'venda',
            preg_match('/^orcamentos?$/u', $entidade) === 1 => 'orcamento',
            preg_match('/^(?:o\.?s\.?|(?:ordem|ordens)(?:\s+de)?\s+servicos?)$/u', $entidade) === 1 => 'ordem_servico',
            preg_match('/^contas?(?:\s+a)?\s+receber$/u', $entidade) === 1 => 'conta_receber',
            preg_match('/^contas?(?:\s+a)?\s+pagar$/u', $entidade) === 1 => 'conta_pagar',
            default => null,
        };
    }

    private static function permissaoDa(string $tipo): string
    {
        return match ($tipo) {
            'cliente' => 'clientes',
            'fornecedor' => 'fornecedores',
            'produto', 'servico' => 'produtos',
            'venda' => 'vendas',
            'orcamento' => 'orcamentos',
            'ordem_servico' => 'ordens_servico',
            default => 'financeiro',
        };
    }

    /** @return Collection<int, Model> */
    private function localizar(string $tipo, string $alvo): Collection
    {
        $consulta = $this->consulta($tipo);
        $numero = self::numeroDoAlvo($alvo);

        if ($numero !== null) {
            // "venda 77" e "os 000012" são o número do documento (numero_seq, indexado e igual no MySQL e no SQLite);
            // "#15", usado nas opções de escolha, é sempre o id. Sem documento com esse número, vale o id.
            if (in_array($tipo, ['venda', 'orcamento', 'ordem_servico'], true) && ! str_starts_with(trim($alvo), '#')) {
                $porNumero = (clone $consulta)->where('numero_seq', $numero)->orderByDesc('id')->limit(6)->get();
                if ($porNumero->isNotEmpty()) {
                    return $porNumero;
                }
            }
            $consulta->whereKey($numero);
        } elseif (in_array($tipo, ['conta_receber', 'conta_pagar'], true)) {
            $consulta->where('descricao', 'like', '%'.addcslashes($alvo, '%_\\').'%');
        } else {
            $consulta->where('nome', 'like', '%'.addcslashes($alvo, '%_\\').'%');
        }

        return $consulta->orderByDesc('id')->limit(6)->get();
    }

    private function localizarPorId(string $tipo, int $id): ?Model
    {
        return $this->consulta($tipo)->whereKey($id)->first();
    }

    private function consulta(string $tipo): Builder
    {
        $classe = match ($tipo) {
            'cliente' => Cliente::class,
            'fornecedor' => Fornecedor::class,
            'produto', 'servico' => Produto::class,
            'venda' => Venda::class,
            'orcamento' => Orcamento::class,
            'ordem_servico' => OrdemServico::class,
            'conta_receber' => ContaReceber::class,
            'conta_pagar' => ContaPagar::class,
        };

        $consulta = $classe::query()->where('empresa_id', $this->usuario()->empresa_id);
        if ($tipo === 'produto') {
            $consulta->where('tipo', '1');
        } elseif ($tipo === 'servico') {
            $consulta->where('tipo', '2');
        }

        return $consulta;
    }

    private static function numeroDoAlvo(string $alvo): ?int
    {
        return preg_match('/^(?:#|n(?:[ºo°]|umero)?\s*)?(\d{1,10})$/iu', trim($alvo), $grupos)
            ? (int) $grupos[1]
            : null;
    }

    private function rotulo(string $tipo, Model $registro): string
    {
        return match ($tipo) {
            'venda' => 'venda nº '.($registro->numero ?: $registro->getKey()),
            'orcamento' => 'orçamento nº '.($registro->numero ?: $registro->getKey()),
            'ordem_servico' => 'ordem de serviço nº '.($registro->numero ?: $registro->getKey()),
            'conta_receber' => 'conta a receber “'.$registro->descricao.'” (#'.$registro->getKey().')',
            'conta_pagar' => 'conta a pagar “'.$registro->descricao.'” (#'.$registro->getKey().')',
            default => $this->nomeEntidade($tipo).' “'.$registro->nome.'”',
        };
    }

    private function nomeEntidade(string $tipo): string
    {
        return match ($tipo) {
            'cliente' => 'cliente',
            'fornecedor' => 'fornecedor',
            'produto' => 'produto',
            'servico' => 'serviço',
            'venda' => 'venda',
            'orcamento' => 'orçamento',
            'ordem_servico' => 'ordem de serviço',
            'conta_receber' => 'conta a receber',
            'conta_pagar' => 'conta a pagar',
        };
    }

    private function feminino(string $tipo): bool
    {
        return in_array($tipo, ['venda', 'ordem_servico', 'conta_receber', 'conta_pagar'], true);
    }

    /** Situação como a tela mostra ("Em andamento"), não o valor gravado ("em_andamento"). */
    private function situacao(string $tipo, Model $registro): string
    {
        if ($registro instanceof OrdemServico) {
            return $registro->situacaoLabel();
        }

        $rotulos = match ($tipo) {
            'venda' => ['em_andamento' => 'Em andamento', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'],
            'orcamento' => ['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'cancelado' => 'Cancelado'],
            default => ['aberta' => 'Aberta', 'parcial' => 'Parcial', 'paga' => 'Paga', 'cancelada' => 'Cancelada'],
        };
        $valor = (string) $registro->situacao;

        return $rotulos[$valor] ?? ucfirst(str_replace('_', ' ', $valor));
    }

    private function resumo(string $tipo, Model $registro): string
    {
        return match ($tipo) {
            'cliente' => implode(' · ', array_filter([
                'Cliente: '.$registro->nome,
                $registro->cpf ? 'CPF '.$registro->cpf : null,
                $registro->cnpj ? 'CNPJ '.$registro->cnpj : null,
                $registro->telefone,
                $registro->email,
            ])),
            'fornecedor' => implode(' · ', array_filter([
                'Fornecedor: '.$registro->nome,
                $registro->cnpj ? 'CNPJ '.$registro->cnpj : null,
                $registro->telefone,
                $registro->email,
            ])),
            'produto', 'servico' => implode(' · ', array_filter([
                ucfirst($this->nomeEntidade($tipo)).': '.$registro->nome,
                'Código '.$registro->codigo,
                'Preço '.self::moeda((float) $registro->preco_venda),
                $registro->controla_estoque ? self::quantidade((float) $registro->estoque_atual).' em estoque' : null,
            ])),
            'venda', 'orcamento' => implode(' · ', [
                ucfirst($this->nomeEntidade($tipo)).' nº '.$registro->numero,
                'Situação: '.$this->situacao($tipo, $registro),
                self::moeda((float) $registro->total),
            ]),
            'ordem_servico' => implode(' · ', [
                'Ordem de serviço nº '.$registro->numero,
                $registro->equipamento,
                'Situação: '.$this->situacao($tipo, $registro),
                self::moeda((float) $registro->total),
            ]),
            'conta_receber', 'conta_pagar' => implode(' · ', [
                ucfirst($this->nomeEntidade($tipo)).': '.$registro->descricao,
                'Vencimento: '.$registro->data_vencimento->format('d/m/Y'),
                'Situação: '.$this->situacao($tipo, $registro),
                self::moeda((float) $registro->valor),
            ]),
        };
    }

    private function detalhesExclusao(string $tipo, Model $registro): array
    {
        return match ($tipo) {
            'venda', 'orcamento', 'ordem_servico' => [
                'Situação: '.$this->situacao($tipo, $registro),
                self::moeda((float) $registro->total),
            ],
            'conta_receber', 'conta_pagar' => [
                'Situação: '.$this->situacao($tipo, $registro),
                self::moeda((float) $registro->valor),
            ],
            'produto', 'servico' => [
                'Código: '.$registro->codigo,
                $registro->controla_estoque ? 'Estoque: '.self::quantidade((float) $registro->estoque_atual) : 'Sem controle de estoque.',
            ],
            default => [],
        };
    }

    private function erroAoExcluir(string $tipo, Model $registro): ?string
    {
        return match ($tipo) {
            'venda' => match (true) {
                $registro->situacao === 'confirmada' => 'Venda confirmada não pode ser excluída. Cancele-a pela tela da venda.',
                // Mesma regra da tela: sem permissão de cancelar vendas, só a venda em andamento pode ser apagada.
                $registro->situacao !== 'em_andamento' && ! $this->usuario()->pode('vendas.cancelar') => 'Venda fechada só pode ser consultada.',
                default => null,
            },
            'orcamento' => $registro->situacao === 'aprovado'
                ? 'Orçamento aprovado não pode ser excluído.'
                : null,
            'ordem_servico' => $registro->situacao === 'faturada'
                ? 'Ordem de serviço faturada não pode ser excluída.'
                : null,
            'conta_receber' => $registro->venda_id
                ? 'Conta vinculada a uma venda não pode ser excluída.'
                : (in_array($registro->situacao, ['paga', 'parcial'], true)
                    ? 'Conta com recebimento não pode ser excluída.'
                    : null),
            'conta_pagar' => $registro->entrada_estoque_id
                ? 'Conta vinculada a uma entrada de estoque não pode ser excluída.'
                : (in_array($registro->situacao, ['paga', 'parcial'], true)
                    ? 'Conta com pagamento não pode ser excluída.'
                    : null),
            default => null,
        };
    }

    private function edicaoDisponivel(string $tipo, Model $registro): bool
    {
        return match ($tipo) {
            'venda' => $registro->situacao === 'em_andamento',
            'orcamento' => $registro->situacao === 'pendente',
            'ordem_servico' => ! in_array($registro->situacao, ['faturada', 'cancelada'], true),
            'conta_receber', 'conta_pagar' => ! in_array($registro->situacao, ['paga', 'cancelada'], true),
            default => true,
        };
    }

    private function link(string $tipo, Model $registro, bool $editar): array
    {
        $route = match ($tipo) {
            'cliente' => $editar ? 'cliente.edit' : 'cliente.index',
            'fornecedor' => $editar ? 'fornecedor.edit' : 'fornecedor.index',
            'produto', 'servico' => $editar ? 'produto.edit' : 'produto.index',
            'venda' => $editar && $this->edicaoDisponivel($tipo, $registro) ? 'venda.edit' : 'venda.show',
            'orcamento' => $editar && $this->edicaoDisponivel($tipo, $registro) ? 'orcamento.edit' : 'orcamento.show',
            'ordem_servico' => $editar && $this->edicaoDisponivel($tipo, $registro) ? 'ordem-servico.edit' : 'ordem-servico.show',
            'conta_receber' => $editar && $this->edicaoDisponivel($tipo, $registro) ? 'conta-receber.edit' : 'conta-receber.show',
            'conta_pagar' => $editar && $this->edicaoDisponivel($tipo, $registro) ? 'conta-pagar.edit' : 'conta-pagar.show',
        };
        $url = in_array($tipo, ['cliente', 'fornecedor', 'produto', 'servico'], true) && ! $editar
            ? route($route, ['busca' => $registro->nome])
            : route($route, $registro);

        return [
            'rotulo' => $editar && $this->edicaoDisponivel($tipo, $registro) ? 'Editar no formulário' : 'Abrir registro',
            'url' => $url,
        ];
    }

    private function urlDetalhe(string $tipo, Model $registro): string
    {
        return $this->link($tipo, $registro, false)['url'];
    }
}
