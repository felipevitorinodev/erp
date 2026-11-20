<?php

namespace Tests\Feature;

use App\Models\Clientes\Cliente;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Fornecedores\Fornecedor;
use App\Models\Funcionarios\Funcionario;
use App\Models\Orcamentos\Orcamento;
use App\Models\OrdensServico\OrdemServico;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Assistente: criar, consultar, editar e excluir cada tipo de registro que ele conhece,
 * com as mesmas travas das telas.
 */
class AssistenteRegistrosTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Sábado, 07/11/2026.
        $this->travelTo(now()->setDate(2026, 11, 7)->setTime(10, 0));

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa A', 'nome_fantasia' => 'Empresa A', 'cnpj' => '11222333000181', 'ativo' => true,
        ]);
        $this->admin = User::factory()->create(['empresa_id' => $this->empresa->id, 'perfil' => 'admin', 'ativo' => true]);
    }

    private function diz(string $texto): TestResponse
    {
        return $this->actingAs($this->admin)
            ->postJson(route('comandos.interpretar'), ['texto' => $texto])
            ->assertOk();
    }

    private function confirmar(TestResponse $previa): TestResponse
    {
        $previa->assertJsonPath('tipo', 'confirmacao');

        return $this->actingAs($this->admin)
            ->postJson(route('comandos.confirmar'), ['token' => $previa->json('token')])
            ->assertOk();
    }

    private function cliente(string $nome): Cliente
    {
        return Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => $nome, 'ativo' => true]);
    }

    private function venda(int $numero, string $situacao, array $extra = []): Venda
    {
        $produto = Produto::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'nome' => 'Martelo'],
            ['tipo' => 1, 'codigo' => '000900', 'preco_venda' => 30, 'estoque_atual' => 5, 'controla_estoque' => true, 'ativo' => true],
        );
        $venda = Venda::create([
            'empresa_id' => $this->empresa->id, 'usuario_id' => $this->admin->id, 'numero' => $numero,
            'data_venda' => '2026-11-07', 'subtotal' => 30, 'desconto' => 0, 'acrescimo' => 0, 'total' => 30,
            'situacao' => $situacao, 'forma_pagamento' => 'Pix', ...$extra,
        ]);
        $venda->itens()->create([
            'produto_id' => $produto->id, 'produto_nome' => $produto->nome, 'produto_codigo' => $produto->codigo,
            'quantidade' => 1, 'preco_unitario' => 30, 'desconto' => 0, 'total' => 30,
        ]);

        return $venda;
    }

    public function test_fornecedor_pessoa_fisica_e_juridica_do_cadastro_a_exclusao(): void
    {
        $this->confirmar($this->diz('cadastrar fornecedor Atacado Sul cnpj 11.222.333/0001-81')
            ->assertJsonPath('detalhes', ['CNPJ 11.222.333/0001-81']))
            ->assertJsonPath('tipo', 'sucesso');

        // Pessoa física com CPF, como aceita o cadastro completo da tela (antes o CPF era descartado).
        $this->confirmar($this->diz('cadastrar fornecedor José Fretes cpf 529.982.247-25')
            ->assertJsonPath('texto', 'Cadastrar o fornecedor José Fretes?')
            ->assertJsonPath('detalhes', ['CPF 529.982.247-25']))
            ->assertJsonPath('tipo', 'sucesso');
        $this->assertDatabaseHas('fornecedores', ['nome' => 'José Fretes', 'cpf' => '529.982.247-25', 'cnpj' => null]);

        // "fornecedor" no singular (antes só "fornecedores" era reconhecido).
        $this->diz('consultar fornecedor Atacado')
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'Fornecedor: Atacado Sul · CNPJ 11.222.333/0001-81');

        $this->diz('editar fornecedor Atacado')->assertJsonPath('tipo', 'pergunta');
        $this->diz('cidade')->assertJsonPath('tipo', 'pergunta');
        $this->confirmar($this->diz('Campinas'))
            ->assertJsonPath('texto', 'Fornecedor atualizado: fornecedor “Atacado Sul”.');
        $this->assertDatabaseHas('fornecedores', ['nome' => 'Atacado Sul', 'cidade' => 'Campinas']);

        // O único documento não pode ser apagado (a tela exige CPF ou CNPJ).
        $this->diz('editar fornecedor José');
        $this->diz('cpf');
        $this->diz('limpar')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', fn (string $texto) => str_starts_with($texto, 'O fornecedor precisa ficar com CPF ou CNPJ.'));
        $this->diz('não');

        $atacado = Fornecedor::where('nome', 'Atacado Sul')->sole();
        $this->confirmar($this->diz('excluir fornecedor Atacado'))
            ->assertJsonPath('texto', 'Fornecedor excluído.');
        $this->assertSoftDeleted($atacado);
    }

    public function test_ordem_de_servico_do_cadastro_a_exclusao(): void
    {
        $this->cliente('Maria Souza');
        $tecnico = Funcionario::create(['empresa_id' => $this->empresa->id, 'nome' => 'Carlos Técnico', 'ativo' => true]);

        $this->diz('abrir os celular cliente Maria defeito tela quebrada');
        $this->confirmar($this->diz('hoje'))->assertJsonPath('tipo', 'sucesso');
        $os = OrdemServico::sole();

        // "os 15" é a ordem de serviço, não o artigo "os"; e "ordem" no singular também vale.
        $this->diz('consultar os '.$os->numero)
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'Ordem de serviço nº '.$os->numero.' · celular · Situação: Aberta · R$ 0,00');

        $this->diz('editar os '.$os->numero)->assertJsonPath('tipo', 'pergunta');
        $this->diz('técnico');
        $this->confirmar($this->diz('Carlos'))
            ->assertJsonPath('texto', 'Ordem de serviço atualizada: ordem de serviço nº '.$os->numero.'.');

        $this->diz('editar ordem de serviço '.$os->numero)->assertJsonPath('tipo', 'pergunta');
        $this->diz('previsão');
        $this->diz('01/11/2026')->assertJsonPath('texto', fn (string $texto) => str_starts_with($texto, 'A previsão não pode ser anterior à abertura.'));
        $this->confirmar($this->diz('20/11/2026'))->assertJsonPath('tipo', 'sucesso');

        $os->refresh();
        $this->assertSame($tecnico->id, (int) $os->funcionario_id);
        $this->assertSame('2026-11-20', $os->data_previsao->toDateString());

        $this->confirmar($this->diz('excluir a os '.$os->numero))
            ->assertJsonPath('texto', 'Ordem de serviço excluída.');
        $this->assertSoftDeleted($os);
    }

    public function test_conta_especifica_e_consulta_por_periodo_nao_se_confundem(): void
    {
        $receber = ContaReceber::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Mensalidade', 'valor' => 89.9,
            'data_vencimento' => '2026-11-09', 'situacao' => 'aberta', 'valor_pago' => 0,
        ]);
        ContaPagar::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Conta de luz', 'valor' => 230,
            'data_vencimento' => '2026-11-20', 'situacao' => 'aberta', 'valor_pago' => 0,
        ]);

        // Uma conta só, pelo número ou pela descrição (antes caía na consulta por período).
        $this->diz('consultar conta a receber '.$receber->id)
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'Conta a receber: Mensalidade · Vencimento: 09/11/2026 · Situação: Aberta · R$ 89,90');
        $this->diz('ver conta a pagar luz')
            ->assertJsonPath('texto', 'Conta a pagar: Conta de luz · Vencimento: 20/11/2026 · Situação: Aberta · R$ 230,00');

        // Exemplos do próprio assistente: consulta por período, não lançamento (antes perguntava "Qual o valor?").
        $this->diz('contas a receber desta semana')
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'Esta semana (02/11/2026 a 08/11/2026), a receber: nenhuma conta com esse vencimento.');
        $this->diz('contas a pagar deste mês')
            ->assertJsonPath('texto', 'Este mês (01/11/2026 a 30/11/2026), a pagar: 1 conta, R$ 230,00.');
        $this->diz('ver conta a receber de segunda')->assertJsonPath('tipo', 'info');

        // O lançamento continua do jeito que se fala.
        $this->diz('conta a receber 150 da Maria')->assertJsonPath('tipo', 'pergunta');
    }

    public function test_venda_em_andamento_edita_datas_com_a_trava_certa_e_exclui(): void
    {
        $venda = $this->venda(77, 'em_andamento', ['data_saida' => '2026-11-10']);
        $this->venda(1, 'cancelada');

        $this->diz('consultar venda 77')
            ->assertJsonPath('texto', 'Venda nº 77 · Situação: Em andamento · R$ 30,00');
        // "venda 1" é a de número 1 (id 2); "#1" é o id, como nas opções de escolha.
        $this->diz('consultar venda 1')->assertJsonPath('texto', 'Venda nº 1 · Situação: Cancelada · R$ 30,00');
        $this->diz('consultar venda #'.$venda->id)->assertJsonPath('texto', 'Venda nº 77 · Situação: Em andamento · R$ 30,00');

        // A data da venda não pode passar da saída (a comparação estava invertida).
        $this->diz('editar venda 77');
        $this->diz('data da venda');
        $this->diz('20/11/2026')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', fn (string $texto) => str_starts_with($texto, 'A data da venda não pode ficar depois da data de saída já informada.'));
        $this->confirmar($this->diz('05/11/2026')
            ->assertJsonPath('detalhes', ['Atual: 07/11/2026', 'Novo: 05/11/2026']))
            ->assertJsonPath('texto', 'Venda atualizada: venda nº 77.');
        $this->assertSame('2026-11-05', $venda->fresh()->data_venda->toDateString());
        $this->assertSame(1, $venda->itens()->count());

        $this->confirmar($this->diz('excluir venda 77')
            ->assertJsonPath('detalhes', ['Situação: Em andamento', 'R$ 30,00']))
            ->assertJsonPath('texto', 'Venda excluída.');
        $this->assertSoftDeleted($venda);
    }

    public function test_excluir_venda_reabre_o_orcamento_de_origem_e_avisa(): void
    {
        $venda = $this->venda(78, 'em_andamento');
        $orcamento = Orcamento::create([
            'empresa_id' => $this->empresa->id, 'usuario_id' => $this->admin->id, 'venda_id' => $venda->id,
            'numero' => '000005', 'data_orcamento' => '2026-11-06', 'subtotal' => 30, 'desconto' => 0,
            'acrescimo' => 0, 'total' => 30, 'situacao' => 'aprovado',
        ]);

        $this->confirmar($this->diz('excluir venda 78'))
            ->assertJsonPath('tipo', 'sucesso')
            ->assertJsonPath('texto', 'Venda excluída. O orçamento #000005 voltou para "Pendente" e pode ser aprovado de novo.');

        $this->assertSame('pendente', $orcamento->fresh()->situacao);
        $this->assertNull($orcamento->fresh()->venda_id);
    }

    public function test_orcamento_e_servico_pelo_gerenciador(): void
    {
        $maria = $this->cliente('Maria Souza');
        $orcamento = Orcamento::create([
            'empresa_id' => $this->empresa->id, 'usuario_id' => $this->admin->id, 'cliente_id' => $maria->id,
            'numero' => '000010', 'data_orcamento' => '2026-11-07', 'subtotal' => 30, 'desconto' => 0,
            'acrescimo' => 0, 'total' => 30, 'situacao' => 'pendente',
        ]);
        $orcamento->itens()->create([
            'produto_id' => Produto::create(['empresa_id' => $this->empresa->id, 'nome' => 'Martelo', 'codigo' => '000901', 'tipo' => 1, 'preco_venda' => 30, 'ativo' => true])->id,
            'produto_nome' => 'Martelo', 'quantidade' => 1, 'preco_unitario' => 30, 'desconto' => 0, 'total' => 30,
        ]);

        // "10" e "000010" acham o mesmo orçamento pelo número, não pelo id.
        $this->diz('consultar orçamento 10')->assertJsonPath('texto', 'Orçamento nº 000010 · Situação: Pendente · R$ 30,00');
        $this->diz('editar orçamento 000010');
        $this->diz('cliente');
        $this->confirmar($this->diz('sem cliente'))->assertJsonPath('texto', 'Orçamento atualizado: orçamento nº 000010.');
        $this->assertNull($orcamento->fresh()->cliente_id);
        $this->assertSame(1, $orcamento->itens()->count());

        $this->confirmar($this->diz('excluir orçamento 000010')
            ->assertJsonPath('detalhes', ['Situação: Pendente', 'R$ 30,00']))
            ->assertJsonPath('texto', 'Orçamento excluído.');
        $this->assertSoftDeleted($orcamento);

        $this->confirmar($this->diz('cadastrar serviço troca de tela 150'))->assertJsonPath('tipo', 'sucesso');
        $servico = Produto::where('nome', 'troca de tela')->sole();
        $this->assertSame(2, (int) $servico->tipo);

        $this->diz('consultar serviço troca')
            ->assertJsonPath('texto', 'Serviço: troca de tela · Código '.$servico->codigo.' · Preço R$ 150,00');
        $this->diz('editar serviço troca');
        $this->diz('preço');
        $this->confirmar($this->diz('180'))->assertJsonPath('texto', 'Serviço atualizado: serviço “troca de tela”.');
        $this->assertSame(180.0, (float) $servico->fresh()->preco_venda);

        $this->confirmar($this->diz('excluir serviço troca de tela'))->assertJsonPath('texto', 'Serviço excluído.');
        $this->assertSoftDeleted($servico);
    }

    public function test_estado_do_cliente_fica_em_maiusculas_e_contas_no_feminino(): void
    {
        $maria = $this->cliente('Maria Souza');

        $this->diz('editar cliente Maria');
        $this->diz('estado');
        $this->confirmar($this->diz('sp')->assertJsonPath('detalhes', ['Atual: não informado', 'Novo: SP']))
            ->assertJsonPath('texto', 'Cliente atualizado: cliente “Maria Souza”.');
        $this->assertSame('SP', $maria->fresh()->estado);

        $conta = ContaPagar::create([
            'empresa_id' => $this->empresa->id, 'descricao' => 'Aluguel', 'valor' => 1000,
            'data_vencimento' => '2026-11-30', 'situacao' => 'aberta', 'valor_pago' => 0,
        ]);
        $this->diz('editar conta a pagar aluguel');
        $this->diz('valor');
        $this->confirmar($this->diz('1.100,00'))
            ->assertJsonPath('texto', 'Conta a pagar atualizada: conta a pagar “Aluguel” (#'.$conta->id.').');
        $this->confirmar($this->diz('excluir conta a pagar aluguel'))->assertJsonPath('texto', 'Conta a pagar excluída.');
        $this->assertSoftDeleted($conta);
    }

    public function test_campo_que_muda_por_outra_rotina_e_explicado(): void
    {
        $produto = Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => 'Parafuso 8mm', 'codigo' => '000777', 'tipo' => 1,
            'preco_venda' => 1, 'estoque_atual' => 50, 'controla_estoque' => true, 'ativo' => true,
        ]);

        $this->diz('editar produto parafuso')->assertJsonPath('tipo', 'pergunta');
        $this->diz('estoque atual')
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'O estoque atual não é digitado: ele muda pela entrada de estoque, pelas vendas e pelo ajuste de estoque, que guardam o histórico.')
            ->assertJsonPath('link.url', route('produto.edit', $produto));
        $this->assertSame(50.0, (float) $produto->fresh()->estoque_atual);

        // A pergunta foi encerrada: a próxima frase é um pedido novo.
        $this->diz('consultar produto parafuso')->assertJsonPath('tipo', 'info');

        $this->venda(90, 'em_andamento');
        $this->diz('editar venda 90');
        $this->diz('itens')->assertJsonPath('texto', 'Itens, descontos e total são alterados na tela, onde o valor é recalculado. Abra o registro para mudar.');
    }

    public function test_registro_de_outra_empresa_nao_aparece(): void
    {
        $outra = Empresa::create(['razao_social' => 'Empresa B', 'nome_fantasia' => 'Empresa B', 'cnpj' => '04252011000110', 'ativo' => true]);
        Fornecedor::create(['empresa_id' => $outra->id, 'nome' => 'Fornecedor Alheio', 'ativo' => true]);
        $os = OrdemServico::create([
            'empresa_id' => $outra->id, 'numero' => '000321', 'data_abertura' => '2026-11-07',
            'equipamento' => 'TV', 'situacao' => 'aberta', 'total' => 0,
        ]);

        $this->diz('excluir fornecedor Alheio')->assertJsonPath('texto', 'Não encontrei esse registro.');
        $this->diz('editar os 000321')->assertJsonPath('texto', 'Não encontrei esse registro.');
        $this->assertNotSoftDeleted($os);
    }
}
