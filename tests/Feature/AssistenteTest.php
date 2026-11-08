<?php

namespace Tests\Feature;

use App\Models\Clientes\Cliente;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Assistente (antigo chat de comandos): conversa, perguntas, confirmação e permissões. */
class AssistenteTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Sábado, 07/11/2026.
        $this->travelTo(now()->setDate(2026, 11, 7)->setTime(10, 0));

        $this->empresa = $this->criarEmpresa('11222333000181');
        $this->admin = $this->criarUsuario($this->empresa, 'admin');
    }

    private function criarEmpresa(string $cnpj): Empresa
    {
        return Empresa::create([
            'razao_social' => 'Empresa '.$cnpj,
            'nome_fantasia' => 'Empresa '.$cnpj,
            'cnpj' => $cnpj,
            'ativo' => true,
        ]);
    }

    private function criarUsuario(Empresa $empresa, string $perfil): User
    {
        return User::factory()->create(['empresa_id' => $empresa->id, 'perfil' => $perfil, 'ativo' => true]);
    }

    private function usuarioCom(array $permissoes): User
    {
        Perfil::create([
            'empresa_id' => $this->empresa->id,
            'slug' => 'perfil-assistente',
            'nome' => 'Perfil do assistente',
            'permissoes' => $permissoes,
            'sistema' => false,
            'ativo' => true,
        ]);

        return $this->criarUsuario($this->empresa, 'perfil-assistente');
    }

    private function diz(string $texto, ?User $usuario = null): TestResponse
    {
        return $this->actingAs($usuario ?? $this->admin)
            ->postJson(route('comandos.interpretar'), ['texto' => $texto])
            ->assertOk();
    }

    private function confirmar(string $token, string $acao = 'confirmar'): TestResponse
    {
        return $this->actingAs($this->admin)
            ->postJson(route('comandos.confirmar'), ['token' => $token, 'acao' => $acao])
            ->assertOk();
    }

    /** @return list<string> */
    private function rotulos(TestResponse $resposta): array
    {
        return array_column($resposta->json('opcoes') ?? [], 'rotulo');
    }

    private function produto(string $nome, float $preco, float $estoque = 10): Produto
    {
        return Produto::create([
            'empresa_id' => $this->empresa->id, 'nome' => $nome, 'tipo' => 1,
            'preco_venda' => $preco, 'estoque_atual' => $estoque, 'controla_estoque' => true, 'ativo' => true,
        ]);
    }

    private function forma(string $nome, string $tipo = 'avista'): FormaPagamento
    {
        return FormaPagamento::create([
            'empresa_id' => $this->empresa->id, 'nome' => $nome, 'tipo' => $tipo,
            'gera_conta_receber' => $tipo !== 'avista', 'dias_vencimento' => $tipo === 'avista' ? 0 : 30, 'ativo' => true,
        ]);
    }

    private function cliente(string $nome): Cliente
    {
        return Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => $nome, 'ativo' => true]);
    }

    public function test_botao_e_rotas_so_para_quem_tem_permissao(): void
    {
        $vendedor = $this->criarUsuario($this->empresa, 'operador');

        $this->actingAs($vendedor)->get('/')->assertOk()->assertDontSee('data-comandos-abrir', false);
        $this->actingAs($vendedor)->postJson(route('comandos.interpretar'), ['texto' => 'ajuda'])->assertForbidden();

        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('data-comandos-abrir', false)
            ->assertSee('Assistente')
            ->assertSee('Nada é salvo sem você confirmar.')
            ->assertSee('data-comandos-enviar="nova venda"', false)
            ->assertDontSee('Chat de comandos');
    }

    public function test_cadastra_cliente_so_depois_de_confirmar_e_token_nao_repete(): void
    {
        $previa = $this->diz('Cadastre cliente Maria Souza cpf 529.982.247-25 telefone (11) 98765-4321')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('texto', 'Cadastrar o cliente Maria Souza?')
            ->assertJsonPath('detalhes', ['CPF 529.982.247-25 · (11) 98765-4321']);

        $this->assertArrayNotHasKey('dados', $previa->json());
        $this->assertDatabaseMissing('clientes', ['nome' => 'Maria Souza']);

        $token = $previa->json('token');
        $this->confirmar($token)
            ->assertJsonPath('tipo', 'sucesso')
            ->assertJsonPath('texto', 'Pronto! Cliente cadastrado: Maria Souza.')
            ->assertJsonPath('previa', 'confirmada');

        $this->assertDatabaseHas('clientes', [
            'empresa_id' => $this->empresa->id,
            'nome' => 'Maria Souza',
            'cpf' => '529.982.247-25',
            'telefone' => '(11) 98765-4321',
        ]);

        $this->confirmar($token)
            ->assertJsonPath('tipo', 'erro')
            ->assertJsonPath('texto', 'Essa confirmação expirou ou já foi usada. Peça de novo.');
        $this->assertSame(1, Cliente::where('nome', 'Maria Souza')->count());
    }

    public function test_responder_sim_salva_e_nao_descarta(): void
    {
        $this->diz('sim')->assertJsonPath('texto', 'Certo! É só me dizer o que precisa.');

        $this->diz('novo cliente João Lima')->assertJsonPath('tipo', 'confirmacao');
        $this->diz('Sim, pode salvar!')->assertJsonPath('tipo', 'sucesso')->assertJsonPath('previa', 'confirmada');
        $this->assertDatabaseHas('clientes', ['nome' => 'João Lima']);

        $this->diz('novo cliente Pedro')->assertJsonPath('tipo', 'confirmacao');
        $this->diz('não, deixa pra lá')
            ->assertJsonPath('texto', 'Tudo bem, não salvei nada.')
            ->assertJsonPath('previa', 'cancelada');
        $this->assertDatabaseMissing('clientes', ['nome' => 'Pedro']);

        // Nada pendente: "sim" não grava a prévia já descartada.
        $this->diz('sim')->assertJsonPath('texto', 'Certo! É só me dizer o que precisa.');
        $this->assertDatabaseMissing('clientes', ['nome' => 'Pedro']);
    }

    public function test_botao_cancelar_descarta_a_previa(): void
    {
        $token = $this->diz('novo cliente Pedro')->json('token');

        $this->confirmar($token, 'cancelar')->assertJsonPath('previa', 'cancelada');
        $this->confirmar($token)->assertJsonPath('tipo', 'erro');
        $this->assertDatabaseMissing('clientes', ['nome' => 'Pedro']);
    }

    public function test_cliente_pergunta_o_nome_e_aceita_nome_parecido_com_tarefa(): void
    {
        $this->diz('cadastre cliente')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', 'Qual o nome do cliente?');

        // "Vanda" é a resposta, não "venda".
        $this->diz('Vanda')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('texto', 'Cadastrar o cliente Vanda?');
    }

    public function test_documento_errado_e_perguntado_sem_repetir_o_numero(): void
    {
        $pergunta = $this->diz('novo cliente João cpf 1234567')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', 'O CPF/CNPJ informado tem 7 dígitos. CPF tem 11 e CNPJ tem 14. Qual é o número certo?');
        $this->assertStringNotContainsString('1234567', $pergunta->getContent());

        $this->diz('529.982.247-25')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('texto', 'Cadastrar o cliente João?')
            ->assertJsonPath('detalhes', ['CPF 529.982.247-25']);

        $this->diz('novo cliente Ana cpf 123')->assertJsonPath('tipo', 'pergunta');
        $this->diz('sem documento')->assertJsonPath('texto', 'Cadastrar o cliente Ana?');
    }

    public function test_cliente_com_problema_recebe_mensagem_clara(): void
    {
        $this->assertStringContainsString('CPF inválido.', $this->diz('novo cliente João cpf 111.111.111-11')->json('texto'));

        Cliente::create(['empresa_id' => $this->empresa->id, 'nome' => 'Ana', 'cpf' => '529.982.247-25', 'ativo' => true]);
        $this->assertStringContainsString(
            'Já existe um cliente com este CPF.',
            $this->diz('cadastre cliente Ana Paula cpf 529.982.247-25')->json('texto'),
        );

        $this->diz('cadastre cliente Ana')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('avisos', ['Já existe um cliente com esse nome.']);
    }

    public function test_cadastra_fornecedor_e_pede_cnpj_no_lugar_do_cpf(): void
    {
        $this->diz('cadastre fornecedor Atacado Sul cpf 529.982.247-25')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', 'Fornecedor é cadastrado com CNPJ, e esse número tem formato de CPF. Qual é o CNPJ?');

        $previa = $this->diz('11.222.333/0001-81')->assertJsonPath('texto', 'Cadastrar o fornecedor Atacado Sul?');
        $this->confirmar($previa->json('token'))->assertJsonPath('tipo', 'sucesso');

        $this->assertDatabaseHas('fornecedores', ['empresa_id' => $this->empresa->id, 'nome' => 'Atacado Sul', 'cnpj' => '11.222.333/0001-81']);
    }

    public function test_erro_de_digitacao_no_pedido_e_corrigido(): void
    {
        $this->diz('cadastar clinete Maria Souza')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('texto', 'Cadastrar o cliente Maria Souza?');

        $this->diz('cadastra a Maria Lima como cliente')
            ->assertJsonPath('texto', 'Cadastrar o cliente Maria Lima?');
    }

    public function test_conta_a_receber_entende_valor_data_cliente_e_descricao(): void
    {
        $maria = $this->cliente('Maria Souza');
        $outraEmpresa = $this->criarEmpresa('11444777000161');
        Cliente::create(['empresa_id' => $outraEmpresa->id, 'nome' => 'Maria Souza', 'ativo' => true]);

        $previa = $this->diz('receber 150,00 do cliente Maria Souza vence 15/12 referente a conserto')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('texto', 'Lançar R$ 150,00 a receber de Maria Souza?')
            ->assertJsonPath('detalhes', ['Vence em 15/12/2026 (em 38 dias)', 'Descrição: conserto']);

        $this->confirmar($previa->json('token'))->assertJsonPath('tipo', 'sucesso');

        $conta = ContaReceber::where('empresa_id', $this->empresa->id)->sole();
        $this->assertSame($maria->id, $conta->cliente_id);
        $this->assertSame('150.00', $conta->valor);
        $this->assertSame('2026-12-15', $conta->data_vencimento->toDateString());
        $this->assertSame('conserto', $conta->descricao);
    }

    public function test_conta_a_receber_do_jeito_que_se_fala(): void
    {
        $this->cliente('Maria Souza');

        $this->diz('a Maria me deve 150 reais para dia 10')
            ->assertJsonPath('texto', 'Lançar R$ 150,00 a receber de Maria Souza?')
            ->assertJsonPath('detalhes', ['Vence em 10/11/2026 (em 3 dias)', 'Descrição: Recebimento de Maria Souza']);

        $this->diz('lançar conta a receber 300 em 30 dias Maria Souza')
            ->assertJsonPath('detalhes', ['Vence em 07/12/2026 (em 30 dias)', 'Descrição: Recebimento de Maria Souza']);
    }

    public function test_conta_pergunta_o_que_falta_um_de_cada_vez(): void
    {
        $this->diz('conta a receber')->assertJsonPath('texto', 'Qual o valor?');
        $this->diz('não sei ainda')->assertJsonPath('texto', 'Não entendi. Qual o valor?');
        $this->diz('150')
            ->assertJsonPath('texto', 'Quando vence?')
            ->assertJsonPath('opcoes.1', ['rotulo' => 'Amanhã', 'enviar' => 'Amanhã']);
        $this->diz('sexta')->assertJsonPath('texto', 'Do que é essa conta?');

        $previa = $this->diz('conserto da bicicleta')
            ->assertJsonPath('texto', 'Lançar R$ 150,00 a receber?')
            ->assertJsonPath('detalhes', ['Vence em 13/11/2026 (em 6 dias)', 'Descrição: conserto da bicicleta']);

        $this->assertNotNull($previa->json('token'));
        $this->diz('ok')->assertJsonPath('tipo', 'sucesso');
        $this->assertSame('2026-11-13', ContaReceber::sole()->data_vencimento->toDateString());
    }

    public function test_conta_a_pagar_corrige_data_e_fornecedor_sem_recomecar(): void
    {
        $this->diz('pagar 350 vence 31/02 referente a energia')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', 'A data 31/02 não existe no calendário. Qual é a data certa?');
        $this->diz('28/02')
            ->assertJsonPath('texto', 'Lançar R$ 350,00 a pagar?')
            ->assertJsonPath('detalhes', ['Vence em 28/02/2027 (em 113 dias)', 'Descrição: energia']);

        $pergunta = $this->diz('pagar 350 vence amanhã fornecedor Inexistente referente a energia')
            ->assertJsonPath('texto', 'Não encontrei o fornecedor “Inexistente”. Qual é o nome certo?');
        $this->assertSame(['Sem fornecedor', 'Cadastrar “Inexistente”'], $this->rotulos($pergunta));

        $previa = $this->diz('sem fornecedor')->assertJsonPath('texto', 'Lançar R$ 350,00 a pagar?');
        $this->confirmar($previa->json('token'));

        $this->assertDatabaseHas('contas_pagar', ['empresa_id' => $this->empresa->id, 'descricao' => 'energia', 'valor' => 350, 'fornecedor_id' => null]);
        $this->assertSame(1, ContaPagar::count());
    }

    public function test_conta_de_luz_do_jeito_que_se_fala(): void
    {
        $this->diz('conta de luz 230 vence sexta')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('texto', 'Lançar R$ 230,00 a pagar?')
            ->assertJsonPath('detalhes', ['Vence em 13/11/2026 (em 6 dias)', 'Descrição: conta de luz']);
    }

    public function test_venda_em_conversa_pergunta_itens_e_pagamento(): void
    {
        $coca = $this->produto('Coca lata', 6.5);
        $this->forma('Pix');
        $this->forma('Dinheiro');

        $this->diz('nova venda')->assertJsonPath('texto', 'O que foi vendido?');
        $this->diz('2 coca lata')
            ->assertJsonPath('texto', 'Como foi pago?')
            ->assertJsonPath('opcoes', [['rotulo' => 'Dinheiro', 'enviar' => 'Dinheiro'], ['rotulo' => 'Pix', 'enviar' => 'Pix']]);
        $this->diz('é no pix')
            ->assertJsonPath('tipo', 'confirmacao')
            ->assertJsonPath('texto', 'Registrar venda de R$ 13,00 no Pix?')
            ->assertJsonPath('itens', [['descricao' => '2 × Coca lata', 'valor' => 'R$ 13,00']]);
        $this->assertSame(0, Venda::count());

        $sucesso = $this->diz('confirma')->assertJsonPath('tipo', 'sucesso');
        $this->assertStringStartsWith('Pronto! Venda nº ', $sucesso->json('texto'));

        $venda = Venda::sole();
        $this->assertSame('confirmada', $venda->situacao);
        $this->assertEquals(13.0, (float) $venda->total);
        $this->assertEquals(8.0, (float) $coca->fresh()->estoque_atual);
    }

    public function test_venda_do_jeito_que_se_fala_e_cliente_com_nome_errado(): void
    {
        $this->produto('Coca lata', 6.5);
        $this->forma('Pix');
        $maria = $this->cliente('Maria Souza');

        $this->diz('a Maria comprou 2 coca lata no pix')
            ->assertJsonPath('texto', 'Registrar venda de R$ 13,00 no Pix para Maria Souza?');

        $pergunta = $this->diz('a Mria comprou 2 coca lata no pix')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', 'Não encontrei o cliente “Mria”. Seria algum destes?');
        $this->assertSame(['Maria Souza', 'Sem cliente', 'Cadastrar “Mria”'], $this->rotulos($pergunta));
        $pergunta->assertJsonPath('opcoes.0.enviar', '#'.$maria->id);

        $this->diz('#'.$maria->id)->assertJsonPath('texto', 'Registrar venda de R$ 13,00 no Pix para Maria Souza?');

        $this->diz('ele comprou 1 coca lata no pix')->assertJsonPath('texto', 'Registrar venda de R$ 6,50 no Pix?');

        $this->diz('a Mria comprou 2 coca lata no pix');
        $this->diz('sem cliente')->assertJsonPath('texto', 'Registrar venda de R$ 13,00 no Pix?');
    }

    public function test_venda_a_prazo_e_produto_ambiguo(): void
    {
        $this->produto('Parafuso 8mm', 1);
        $dezMm = $this->produto('Parafuso 10mm', 1);
        $this->forma('Dinheiro');
        $this->forma('Boleto', 'prazo');

        $this->diz('vendi 3 parafuso 8mm no boleto')
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'Venda a prazo precisa das parcelas, então é feita na tela de venda.')
            ->assertJsonPath('link.url', route('venda.create'));

        $pergunta = $this->diz('vendi 3 parafuso no dinheiro')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', 'Achei mais de um “parafuso”. Qual deles?');
        $this->assertSame(['Parafuso 10mm · R$ 1,00', 'Parafuso 8mm · R$ 1,00'], $this->rotulos($pergunta));

        $this->diz('#'.$dezMm->id)
            ->assertJsonPath('texto', 'Registrar venda de R$ 3,00 no Dinheiro?')
            ->assertJsonPath('itens.0.descricao', '3 × Parafuso 10mm');
    }

    public function test_consulta_de_preco_e_estoque(): void
    {
        $coca = $this->produto('Coca lata', 6.5);
        $this->produto('Parafuso 8mm', 1);

        $this->diz('quanto custa a coca lata?')
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'Coca lata: R$ 6,50')
            ->assertJsonPath('detalhes', ['10 em estoque'])
            ->assertJsonPath('opcoes', [['rotulo' => 'Vender 1', 'enviar' => 'vender 1 #'.$coca->id]]);

        $this->diz('tem coca no estoque?')->assertJsonPath('texto', 'Coca lata: R$ 6,50');

        $parecido = $this->diz('quanto custa o parafuzo 8mm')
            ->assertJsonPath('tipo', 'pergunta')
            ->assertJsonPath('texto', 'Não encontrei “parafuzo 8mm”. Seria algum destes?');
        $this->assertSame('Parafuso 8mm · R$ 1,00', $this->rotulos($parecido)[0]);
    }

    public function test_resumo_do_dia(): void
    {
        $this->diz('quanto vendi hoje?')
            ->assertJsonPath('tipo', 'info')
            ->assertJsonPath('texto', 'Ainda não há vendas confirmadas hoje.');
    }

    public function test_conversa_saudacao_ajuda_e_pedido_incompleto(): void
    {
        $this->assertStringStartsWith('Boa noite, ', $this->diz('boa noite')->json('texto'));
        $this->diz('obrigado!')->assertJsonPath('texto', 'Por nada! Se precisar, é só escrever.');

        $this->diz('cadastrar')
            ->assertJsonPath('texto', 'Cadastrar o quê?')
            ->assertJsonPath('opcoes', [
                ['rotulo' => 'Cadastrar cliente', 'enviar' => 'cadastrar cliente'],
                ['rotulo' => 'Cadastrar fornecedor', 'enviar' => 'cadastrar fornecedor'],
            ]);

        $this->assertContains('Nova venda', $this->rotulos($this->diz('o que você faz?')));
    }

    public function test_pedido_novo_no_meio_de_uma_pergunta_troca_de_assunto(): void
    {
        $this->diz('cadastre cliente')->assertJsonPath('texto', 'Qual o nome do cliente?');
        $this->diz('conta a pagar')->assertJsonPath('texto', 'Qual o valor?');
        $this->diz('não')->assertJsonPath('texto', 'Tudo bem, deixei de lado.');
    }

    public function test_quando_nao_entende_sugere_o_mais_provavel(): void
    {
        $this->diz('fiado da padaria')
            ->assertJsonPath('texto', 'Não entendi bem. Você quer fazer alguma destas?')
            ->assertJsonPath('opcoes.0.rotulo', 'Conta a receber');

        $resposta = $this->diz('xyz qwe');
        $resposta->assertJsonPath('texto', 'Não entendi. Escreva de outro jeito ou escolha uma opção:');
        $this->assertNotEmpty($resposta->json('opcoes'));
    }

    public function test_tarefas_respeitam_o_perfil(): void
    {
        $usuario = $this->usuarioCom(['comandos', 'financeiro']);

        $rotulos = $this->rotulos($this->diz('ajuda', $usuario));
        $this->assertContains('Conta a receber', $rotulos);
        $this->assertNotContains('Nova venda', $rotulos);

        $this->diz('cadastre cliente Maria', $usuario)
            ->assertJsonPath('tipo', 'erro')
            ->assertJsonPath('texto', 'Seu perfil não pode usar “Cadastrar cliente”. Se precisar, peça a liberação ao administrador.');

        // Sem permissão de cadastrar cliente, a pergunta não oferece "Cadastrar".
        $this->assertSame(['Sem cliente'], $this->rotulos($this->diz('receber 50 amanhã do cliente Fulano', $usuario)));
    }

    public function test_texto_vazio_ou_longo_demais(): void
    {
        $this->actingAs($this->admin)->postJson(route('comandos.interpretar'), ['texto' => ''])
            ->assertStatus(422)->assertJsonPath('errors.texto.0', 'Escreva o que você precisa.');

        $this->actingAs($this->admin)->postJson(route('comandos.interpretar'), ['texto' => str_repeat('a', 301)])
            ->assertStatus(422)->assertJsonPath('errors.texto.0', 'Mensagem longa demais: use até 300 caracteres.');

        $this->actingAs($this->admin)->postJson(route('comandos.confirmar'), ['token' => str_repeat('a', 32), 'acao' => 'apagar'])
            ->assertStatus(422);
    }
}
