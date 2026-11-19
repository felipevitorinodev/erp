<?php

namespace App\Comandos\Rotinas;

use App\Comandos\Leitor;
use App\Comandos\Resposta;
use App\Comandos\Rotina;
use App\Models\Clientes\Cliente;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Fornecedores\Fornecedor;
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
use App\Support\Auditoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Consulta, abre a edição ou exclui com confirmação os registros das rotinas do assistente. */
final class GerenciarRegistro extends Rotina
{
    private const ENTIDADE = '/^(?<entidade>contas?\s+a\s+receber|contas?\s+receber|contas?\s+a\s+pagar|contas?\s+pagar|ordens?\s+de\s+servi[cç]os?|ordens?\s+servi[cç]os?|os|orcamentos?|vendas?|clientes?|fornecedores?|produtos?|servi[cç]os?)\b\s*(?<alvo>.*)$/u';

    public function __construct(
        private readonly ClienteRepository $clientes,
        private readonly FornecedorRepository $fornecedores,
        private readonly ProdutoRepository $produtos,
        private readonly VendaRepository $vendas,
        private readonly OrcamentoRepository $orcamentos,
        private readonly OrdemServicoRepository $ordens,
        private readonly ContaReceberRepository $contasReceber,
        private readonly ContaPagarRepository $contasPagar,
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

    public function preparar(string $argumento): Resposta
    {
        $pedido = Leitor::grupos(
            '/^(?<acao>consult\w*|ver|mostr\w*|busc\w*|procur\w*|pesquis\w*|list\w*|detalh\w*|visualiz\w*|edit\w*|alter\w*|atualiz\w*|modific\w*|mud\w*|exclu\w*|apag\w*|delet\w*|remov\w*)\s+(?:(?:o|a|os|as|um|uma)\s+)?(?<resto>.+)$/u',
            $argumento,
        );
        if ($pedido === null || ($entidade = Leitor::grupos(self::ENTIDADE, $pedido['resto'])) === null) {
            return Resposta::erro('Diga qual registro deseja consultar, editar ou excluir.');
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
            );
        }

        $registro = $registros->first();

        return match ($acao) {
            'consultar' => Resposta::info($this->resumo($tipo, $registro), link: $this->link($tipo, $registro, false)),
            'editar' => Resposta::info(
                $this->edicaoDisponivel($tipo, $registro)
                    ? 'Abra o formulário para editar '.$this->rotulo($tipo, $registro).'.'
                    : 'Este registro não pode mais ser editado; confira sua situação.',
                link: $this->link($tipo, $registro, true),
            ),
            'excluir' => Resposta::confirmacao(
                $this->chave(),
                'Excluir '.$this->rotulo($tipo, $registro).'? Essa ação não poderá ser desfeita.',
                ['entidade' => $tipo, 'id' => (int) $registro->getKey()],
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

        if ($erro = $this->erroAoExcluir($tipo, $registro)) {
            return Resposta::info($erro, link: ['rotulo' => 'Abrir registro', 'url' => $this->urlDetalhe($tipo, $registro)]);
        }

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

        if ($tipo === 'produto' || $tipo === 'servico') {
            Auditoria::registrar('produto.excluido', 'produto', $id, [
                'codigo' => $registro->codigo,
                'estoque_atual' => (float) $registro->estoque_atual,
            ]);
        } elseif ($tipo === 'venda') {
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
            ucfirst($this->nomeEntidade($tipo)).' excluído.',
            registro: ['entidade' => $tipo, 'id' => $id],
        );
    }

    public function completar(string $argumento, string $campo, string $resposta, ?string $termo): string
    {
        return parent::completar($argumento, $campo, $resposta, $termo);
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
            preg_match('/^fornecedores?$/u', $entidade) === 1 => 'fornecedor',
            preg_match('/^produtos?$/u', $entidade) === 1 => 'produto',
            preg_match('/^servicos?$/u', $entidade) === 1 => 'servico',
            preg_match('/^vendas?$/u', $entidade) === 1 => 'venda',
            preg_match('/^orcamentos?$/u', $entidade) === 1 => 'orcamento',
            preg_match('/^(?:os|ordens?(?:\s+de)?\s+servicos?)$/u', $entidade) === 1 => 'ordem_servico',
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

    /** @return \Illuminate\Support\Collection<int, Model> */
    private function localizar(string $tipo, string $alvo): \Illuminate\Support\Collection
    {
        $consulta = $this->consulta($tipo);
        $numero = self::numeroDoAlvo($alvo);

        if ($numero !== null) {
            $consulta->where(function (Builder $q) use ($numero, $tipo) {
                $q->whereKey($numero);
                if (in_array($tipo, ['venda', 'orcamento', 'ordem_servico'], true)) {
                    $q->orWhere('numero', $numero);
                }
            });
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
                'Situação: '.$registro->situacao,
                self::moeda((float) $registro->total),
            ]),
            'ordem_servico' => implode(' · ', [
                'Ordem de serviço nº '.$registro->numero,
                $registro->equipamento,
                'Situação: '.$registro->situacao,
                self::moeda((float) $registro->total),
            ]),
            'conta_receber', 'conta_pagar' => implode(' · ', [
                ucfirst($this->nomeEntidade($tipo)).': '.$registro->descricao,
                'Vencimento: '.$registro->data_vencimento->format('d/m/Y'),
                'Situação: '.$registro->situacao,
                self::moeda((float) $registro->valor),
            ]),
        };
    }

    private function detalhesExclusao(string $tipo, Model $registro): array
    {
        return match ($tipo) {
            'venda', 'orcamento', 'ordem_servico' => [
                'Situação: '.$registro->situacao,
                self::moeda((float) $registro->total),
            ],
            'conta_receber', 'conta_pagar' => [
                'Situação: '.$registro->situacao,
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
            'venda' => $registro->situacao === 'confirmada'
                ? 'Venda confirmada não pode ser excluída. Cancele-a pela tela da venda.'
                : null,
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
