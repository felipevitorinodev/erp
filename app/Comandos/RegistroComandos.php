<?php

namespace App\Comandos;

use App\Comandos\Rotinas\AbrirOrdemServico;
use App\Comandos\Rotinas\CadastrarCliente;
use App\Comandos\Rotinas\CadastrarFornecedor;
use App\Comandos\Rotinas\CadastrarProduto;
use App\Comandos\Rotinas\CadastrarServico;
use App\Comandos\Rotinas\ConsultarContas;
use App\Comandos\Rotinas\ConsultarProduto;
use App\Comandos\Rotinas\ConsultarVendas;
use App\Comandos\Rotinas\LancarContaPagar;
use App\Comandos\Rotinas\LancarContaReceber;
use App\Comandos\Rotinas\RegistrarOrcamento;
use App\Comandos\Rotinas\RegistrarVenda;
use App\Comandos\Rotinas\ResumoDoDia;
use App\Models\Usuarios\User;
use Illuminate\Contracts\Container\Container;

/** Rotinas disponíveis no assistente. Para incluir uma nova, crie a classe em Rotinas/ e coloque aqui. */
final class RegistroComandos
{
    /**
     * A ordem importa: padrões mais específicos antes ("vendas de hoje" antes de "vendas de ontem";
     * "contas que vencem" antes de "conta a receber"; "a Maria vai me pagar" antes de "pagar").
     */
    private const ROTINAS = [
        ResumoDoDia::class,
        ConsultarVendas::class,
        RegistrarVenda::class,
        RegistrarOrcamento::class,
        AbrirOrdemServico::class,
        CadastrarCliente::class,
        CadastrarFornecedor::class,
        CadastrarProduto::class,
        CadastrarServico::class,
        ConsultarContas::class,
        LancarContaReceber::class,
        LancarContaPagar::class,
        ConsultarProduto::class,
    ];

    /** Ordem em que as tarefas aparecem para o usuário: as mais usadas no dia a dia primeiro. */
    private const ORDEM_DE_USO = [
        'venda',
        'orcamento',
        'ordem_servico',
        'conta_receber',
        'produto',
        'consulta_venda',
        'consulta_contas',
        'cadastrar_produto',
        'cadastrar_servico',
        'cliente',
        'conta_pagar',
        'fornecedor',
        'resumo',
    ];

    /** @var list<Comando>|null */
    private ?array $instancias = null;

    public function __construct(private readonly Container $container) {}

    /** @return list<Comando> */
    public function todos(): array
    {
        return $this->instancias ??= array_map(fn (string $classe) => $this->container->make($classe), self::ROTINAS);
    }

    /** @return list<Comando> as que o perfil pode usar, na ordem de uso (para botões e sugestões) */
    public function permitidos(User $usuario): array
    {
        $permitidos = array_values(array_filter($this->todos(), fn (Comando $c) => $usuario->pode($c->permissao())));
        usort($permitidos, fn (Comando $a, Comando $b) => self::posicao($a) <=> self::posicao($b));

        return $permitidos;
    }

    /** Exemplo que melhor mostra o assistente em ação, entre as rotinas que o perfil pode usar. */
    public function exemploPrincipal(User $usuario): ?string
    {
        return ($this->permitidos($usuario)[0] ?? null)?->exemplos()[0];
    }

    private static function posicao(Comando $comando): int
    {
        $posicao = array_search($comando->chave(), self::ORDEM_DE_USO, true);

        return $posicao === false ? count(self::ORDEM_DE_USO) : $posicao;
    }

    public function buscar(string $chave): ?Comando
    {
        foreach ($this->todos() as $comando) {
            if ($comando->chave() === $chave) {
                return $comando;
            }
        }

        return null;
    }
}
