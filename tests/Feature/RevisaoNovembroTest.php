<?php

namespace Tests\Feature;

use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Estoque\EntradaEstoque;
use App\Models\Estoque\EntradaEstoqueItem;
use App\Models\Estoque\MovimentacaoEstoque;
use App\Models\FormasPagamento\FormaPagamento;
use App\Models\Orcamentos\Orcamento;
use App\Models\OrdensServico\OrdemServico;
use App\Models\Produtos\Produto;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use App\Repositories\Estoque\EntradaEstoqueRepository;
use App\Support\Auditoria;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Correções da revisão de 07/11/2026: custo na busca, vendas canceladas na busca,
 * reenvio do PDV, parcelas fechando o total, trilha de auditoria, preço mínimo,
 * XML da entrada, código automático do produto e "esqueci a senha" sem SMTP.
 */
class RevisaoNovembroTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    private User $admin;

    private Produto $produto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->admin = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
        $this->produto = Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto Teste',
            'codigo' => '000001',
            'tipo' => 1,
            'preco_custo' => 4,
            'preco_venda' => 10,
            'estoque_atual' => 10,
            'controla_estoque' => true,
            'ativo' => true,
        ]);
        FormaPagamento::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Dinheiro',
            'tipo' => 'avista',
            'gera_conta_receber' => false,
            'ativo' => true,
        ]);
    }

    private function vendedor(): User
    {
        return User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'vendedor',
            'ativo' => true,
        ]);
    }

    private function vendedorComPermissoes(array $permissoes): User
    {
        Perfil::updateOrCreate(
            ['empresa_id' => $this->empresa->id, 'slug' => 'vendedor'],
            ['nome' => 'Vendedor', 'permissoes' => $permissoes, 'sistema' => true, 'ativo' => true],
        );
        User::esquecerPerfisEmCache($this->empresa->id);

        return $this->vendedor();
    }

    private function envioPdv(array $extra = []): array
    {
        return array_merge([
            'data_venda' => now()->toDateString(),
            'situacao' => 'confirmada',
            'forma_pagamento' => 'Dinheiro',
            'chave_idempotencia' => 'chave-teste-1',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '2,000', 'preco_unitario' => '10,00', 'desconto' => '0,00'],
            ],
        ], $extra);
    }

    public function test_busca_de_produtos_esconde_o_custo_de_quem_nao_cuida_de_produtos(): void
    {
        $this->actingAs($this->vendedor())
            ->getJson(route('api.produtos.busca', ['q' => 'Produto']))
            ->assertOk()
            ->assertJsonPath('0.id', $this->produto->id)
            ->assertJsonPath('0.preco_custo', null);

        $resposta = $this->actingAs($this->admin)
            ->getJson(route('api.produtos.busca', ['q' => 'Produto']))
            ->assertOk();

        $this->assertEquals(4.0, (float) $resposta->json('0.preco_custo'));
    }

    public function test_busca_de_vendas_esconde_canceladas_de_quem_nao_tem_permissao(): void
    {
        foreach (['confirmada' => '000001', 'cancelada' => '000002'] as $situacao => $numero) {
            Venda::create([
                'empresa_id' => $this->empresa->id,
                'numero' => $numero,
                'data_venda' => now()->toDateString(),
                'total' => 10,
                'situacao' => $situacao,
            ]);
        }

        $this->actingAs($this->vendedor())
            ->getJson(route('api.vendas.busca'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.situacao', 'confirmada');

        $this->actingAs($this->admin)
            ->getJson(route('api.vendas.busca'))
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_pdv_reenviado_com_a_mesma_chave_nao_duplica_a_venda_nem_o_estoque(): void
    {
        $this->actingAs($this->admin);

        $primeira = $this->postJson(route('venda.store'), $this->envioPdv())->assertCreated();
        $segunda = $this->postJson(route('venda.store'), $this->envioPdv())
            ->assertOk()
            ->assertJsonPath('repetida', true);

        $this->assertSame($primeira->json('id'), $segunda->json('id'));
        $this->assertSame(1, Venda::count());
        $this->assertEquals(8.0, (float) $this->produto->fresh()->estoque_atual);
        $this->assertSame(1, MovimentacaoEstoque::where('origem', 'venda')->count());
    }

    public function test_pdv_com_a_mesma_chave_e_carrinho_diferente_gera_outra_venda(): void
    {
        $this->actingAs($this->admin);

        $this->postJson(route('venda.store'), $this->envioPdv())->assertCreated();
        $this->postJson(route('venda.store'), $this->envioPdv([
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '1,000', 'preco_unitario' => '10,00', 'desconto' => '0,00'],
            ],
        ]))->assertCreated();

        $this->assertSame(2, Venda::count());
        $this->assertEquals(7.0, (float) $this->produto->fresh()->estoque_atual);
    }

    public function test_venda_sem_chave_segue_o_comportamento_anterior(): void
    {
        $this->actingAs($this->admin);

        $envio = $this->envioPdv(['chave_idempotencia' => null]);
        $this->postJson(route('venda.store'), $envio)->assertCreated();
        $this->postJson(route('venda.store'), $envio)->assertCreated();

        $this->assertSame(2, Venda::count());
    }

    public function test_chave_com_caracteres_invalidos_e_recusada(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('venda.store'), $this->envioPdv(['chave_idempotencia' => 'abc;drop']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('chave_idempotencia');

        $this->assertSame(0, Venda::count());
    }

    public function test_pdv_recebe_422_em_json_quando_o_formulario_e_invalido(): void
    {
        $outraEmpresa = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '11444777000161']);
        $produtoDeOutraEmpresa = Produto::create([
            'empresa_id' => $outraEmpresa->id,
            'nome' => 'Produto B',
            'tipo' => 1,
            'preco_venda' => 5,
        ]);

        // Antes o erro do FormRequest virava redirecionamento 302 e o PDV mostrava "sessão expirada".
        $this->actingAs($this->admin)
            ->postJson(route('venda.store'), $this->envioPdv([
                'itens' => [
                    ['produto_id' => $produtoDeOutraEmpresa->id, 'quantidade' => '1,000', 'preco_unitario' => '5,00'],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('itens.0.produto_id');

        $this->assertSame(0, Venda::count());
    }

    public function test_pagina_comum_continua_redirecionando_no_erro_de_validacao(): void
    {
        $this->actingAs($this->admin)
            ->from(route('venda.create'))
            ->post(route('venda.store'), ['data_venda' => now()->toDateString()])
            ->assertRedirect(route('venda.create'))
            ->assertSessionHasErrors('itens');
    }

    public function test_parcelas_com_diferenca_de_centavos_fecham_exatamente_o_total_da_venda(): void
    {
        FormaPagamento::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Boleto',
            'tipo' => 'prazo',
            'gera_conta_receber' => true,
            'dias_vencimento' => 30,
            'ativo' => true,
        ]);

        $vencimento = now()->addDays(30)->toDateString();
        $resposta = $this->actingAs($this->admin)->postJson(route('venda.store'), [
            'data_venda' => now()->toDateString(),
            'forma_pagamento' => 'Boleto',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '3,000', 'preco_unitario' => '10,00'],
            ],
            'parcelas' => [
                ['vencimento' => $vencimento, 'valor' => '9,99'],
                ['vencimento' => $vencimento, 'valor' => '9,99'],
                ['vencimento' => $vencimento, 'valor' => '9,99'],
            ],
        ])->assertCreated();

        $contas = ContaReceber::where('venda_id', $resposta->json('id'))->orderBy('id')->get();

        $this->assertCount(3, $contas);
        $this->assertEquals(30.0, round((float) $contas->sum('valor'), 2));
        $this->assertEquals(10.02, (float) $contas->last()->valor);
    }

    public function test_cancelamento_de_venda_fica_na_trilha_de_auditoria(): void
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'auditoria');
        config(['logging.channels.auditoria' => ['driver' => 'single', 'path' => $arquivo, 'level' => 'info']]);

        $venda = Venda::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_venda' => now()->toDateString(),
            'total' => 10,
            'situacao' => 'em_andamento',
        ]);

        $this->actingAs($this->admin)
            ->post(route('venda.cancelar', $venda))
            ->assertSessionHas('success');

        $conteudo = (string) file_get_contents($arquivo);
        @unlink($arquivo);

        $this->assertStringContainsString('venda.cancelada', $conteudo);
        $this->assertStringContainsString('"usuario_id":'.$this->admin->id, $conteudo);
        $this->assertStringContainsString('"situacao_anterior":"em_andamento"', $conteudo);
    }

    public function test_falha_ao_gravar_a_auditoria_nao_interrompe_a_operacao(): void
    {
        config(['logging.channels.auditoria' => [
            'driver' => 'single',
            'path' => '/proc/caminho-inexistente/auditoria.log',
            'level' => 'info',
        ]]);
        $errorLog = ini_set('error_log', '/dev/null');

        try {
            $this->actingAs($this->admin);
            Auditoria::registrar('teste.falha', 'teste', 1);

            $venda = Venda::create([
                'empresa_id' => $this->empresa->id,
                'numero' => '000001',
                'data_venda' => now()->toDateString(),
                'total' => 10,
                'situacao' => 'em_andamento',
            ]);

            $this->post(route('venda.cancelar', $venda))->assertSessionHas('success');
            $this->assertSame('cancelada', $venda->fresh()->situacao);
        } finally {
            ini_set('error_log', $errorLog === false ? '' : $errorLog);
        }
    }

    public function test_vendedor_nao_vende_abaixo_do_preco_minimo_no_pdv(): void
    {
        $this->produto->update(['preco_minimo' => 9]);

        $this->actingAs($this->vendedor())
            ->postJson(route('venda.store'), $this->envioPdv([
                'itens' => [
                    ['produto_id' => $this->produto->id, 'quantidade' => '2,000', 'preco_unitario' => '8,00', 'desconto' => '0,00'],
                ],
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('itens');

        $this->assertSame(0, Venda::count());
        $this->assertEquals(10.0, (float) $this->produto->fresh()->estoque_atual);
    }

    public function test_desconto_geral_conta_no_preco_minimo(): void
    {
        $this->produto->update(['preco_minimo' => 9]);
        $this->actingAs($this->vendedor());

        // 2 × 10,00 − 3,00 = 8,50 por unidade.
        $this->postJson(route('venda.store'), $this->envioPdv(['desconto' => '3,00', 'chave_idempotencia' => null]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('itens');

        // 2 × 10,00 − 2,00 = 9,00 por unidade: no limite, passa.
        $this->postJson(route('venda.store'), $this->envioPdv(['desconto' => '2,00', 'chave_idempotencia' => null]))
            ->assertCreated();

        $this->assertSame(1, Venda::count());
    }

    public function test_admin_e_perfil_com_a_permissao_vendem_abaixo_do_minimo(): void
    {
        $this->produto->update(['preco_minimo' => 9]);
        $abaixo = [
            'chave_idempotencia' => null,
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '1,000', 'preco_unitario' => '8,00', 'desconto' => '0,00'],
            ],
        ];

        $this->actingAs($this->admin)->postJson(route('venda.store'), $this->envioPdv($abaixo))->assertCreated();

        $vendedor = $this->vendedorComPermissoes(['vendas', 'vendas.alterar_preco']);
        $this->actingAs($vendedor)->postJson(route('venda.store'), $this->envioPdv($abaixo))->assertCreated();

        $this->assertSame(2, Venda::count());
    }

    public function test_orcamento_abaixo_do_minimo_e_recusado_para_quem_nao_tem_a_permissao(): void
    {
        $this->produto->update(['preco_minimo' => 9]);

        $this->actingAs($this->vendedorComPermissoes(['vendas', 'orcamentos']))
            ->post(route('orcamento.store'), [
                'data_orcamento' => now()->toDateString(),
                'itens' => [
                    ['produto_id' => $this->produto->id, 'quantidade' => '1', 'preco_unitario' => '5,00', 'desconto' => '0'],
                ],
            ])
            ->assertSessionHasErrors('itens');

        $this->assertSame(0, Orcamento::count());
    }

    public function test_preco_minimo_maior_que_o_de_venda_e_recusado_no_cadastro(): void
    {
        $this->actingAs($this->admin)
            ->post(route('produto.store'), [
                'empresa_id' => $this->empresa->id,
                'codigo' => '000777',
                'nome' => 'Produto com mínimo errado',
                'tipo' => 1,
                'preco_venda' => '10,00',
                'preco_minimo' => '12,00',
            ])
            ->assertSessionHasErrors('preco_minimo');

        $this->assertFalse(Produto::where('codigo', '000777')->exists());
    }

    public function test_codigo_automatico_nao_reaproveita_o_codigo_de_produto_excluido(): void
    {
        Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto excluído',
            'codigo' => '000050',
            'tipo' => 1,
            'preco_venda' => 1,
        ])->delete();

        $this->actingAs($this->admin)
            ->post(route('produto.store'), [
                'empresa_id' => $this->empresa->id,
                'nome' => 'Produto sem código',
                'tipo' => 1,
                'preco_venda' => '5,00',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('000051', Produto::where('nome', 'Produto sem código')->value('codigo'));
    }

    public function test_excluir_entrada_libera_a_chave_e_mantem_o_xml_do_fornecedor(): void
    {
        $chave = str_repeat('3', 44);
        $entrada = EntradaEstoque::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_entrada' => now()->toDateString(),
            'subtotal' => 0,
            'total' => 0,
            'situacao' => 'rascunho',
            'chave_acesso' => $chave,
            'xml_importado' => '<nfeProc>xml do fornecedor</nfeProc>',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('entrada-estoque.destroy', $entrada))
            ->assertSessionHas('success');

        $excluida = EntradaEstoque::onlyTrashed()->findOrFail($entrada->id);
        $this->assertNull($excluida->chave_acesso);
        $this->assertSame('<nfeProc>xml do fornecedor</nfeProc>', $excluida->xml_importado);
    }

    public function test_esqueci_a_senha_fica_indisponivel_em_producao_sem_smtp(): void
    {
        $this->app['env'] = 'production';
        config(['mail.default' => 'log']);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee(route('password.request'), false)
            ->assertSee('Fale com o administrador');

        $this->get(route('password.request'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');

        // Fora do ambiente "testing" o CSRF volta a valer; aqui só interessa a regra do e-mail.
        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('password.email'), ['email' => $this->admin->email])
            ->assertRedirect(route('login'))
            ->assertSessionHas('warning');

        config(['mail.default' => 'smtp']);
        $this->get(route('login'))->assertSee(route('password.request'), false);
    }

    public function test_codigo_repetido_e_recusado_no_cadastro_mesmo_de_produto_excluido(): void
    {
        Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Produto excluído',
            'codigo' => '000777',
            'tipo' => 1,
            'preco_venda' => 1,
        ])->delete();

        $this->actingAs($this->admin)
            ->post(route('produto.store'), [
                'empresa_id' => $this->empresa->id,
                'codigo' => '000777',
                'nome' => 'Produto novo',
                'tipo' => 1,
                'preco_venda' => '5,00',
            ])
            ->assertSessionHasErrors('codigo');

        $this->assertSame(1, Produto::withTrashed()->where('codigo', '000777')->count());
    }

    public function test_indice_unico_impede_codigo_repetido_na_mesma_empresa(): void
    {
        $outraEmpresa = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '11444777000161']);
        $produto = fn (int $empresaId, ?string $codigo) => Produto::create([
            'empresa_id' => $empresaId,
            'nome' => 'Produto',
            'codigo' => $codigo,
            'tipo' => 1,
            'preco_venda' => 1,
        ]);

        $produto($outraEmpresa->id, '000001');
        $produto($this->empresa->id, null);
        $produto($this->empresa->id, null);

        $this->expectException(UniqueConstraintViolationException::class);
        $produto($this->empresa->id, '000001');
    }

    public function test_migration_do_indice_nao_derruba_o_deploy_quando_ha_codigo_repetido(): void
    {
        $migration = require database_path('migrations/2026_11_07_000003_unique_codigo_produtos.php');
        $migration->down();
        $this->assertFalse(Schema::hasIndex('produtos', 'produtos_empresa_codigo_unique'));

        $repetido = DB::table('produtos')->insertGetId([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Repetido',
            'codigo' => '000001',
            'tipo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::spy();
        $migration->up();
        $this->assertFalse(Schema::hasIndex('produtos', 'produtos_empresa_codigo_unique'));
        Log::shouldHaveReceived('warning')->once();

        DB::table('produtos')->where('id', $repetido)->delete();
        $migration->up();
        $this->assertTrue(Schema::hasIndex('produtos', 'produtos_empresa_codigo_unique'));
    }

    public function test_entrada_com_codigo_de_produto_excluido_cria_o_produto_com_codigo_automatico(): void
    {
        Produto::create([
            'empresa_id' => $this->empresa->id,
            'nome' => 'Antigo',
            'codigo' => 'FORN-10',
            'tipo' => 1,
            'preco_venda' => 1,
        ])->delete();

        $entrada = EntradaEstoque::create([
            'empresa_id' => $this->empresa->id,
            'numero' => '000001',
            'data_entrada' => now()->toDateString(),
            'subtotal' => 20,
            'total' => 20,
            'situacao' => 'rascunho',
        ]);
        EntradaEstoqueItem::create([
            'entrada_estoque_id' => $entrada->id,
            'produto_nome' => 'Item do fornecedor',
            'produto_codigo' => 'FORN-10',
            'quantidade' => 2,
            'preco_unitario' => 10,
            'total' => 20,
        ]);

        $this->actingAs($this->admin);
        app(EntradaEstoqueRepository::class)->confirmar($entrada->load('itens'));

        $novo = Produto::where('nome', 'Item do fornecedor')->firstOrFail();
        $this->assertSame('AUTO00001', $novo->codigo);
        $this->assertEquals(2.0, (float) $novo->estoque_atual);
    }

    private function orcamentoAprovado(): array
    {
        $this->actingAs($this->admin)->post(route('orcamento.store'), [
            'data_orcamento' => now()->toDateString(),
            'forma_pagamento' => 'Dinheiro',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '2', 'preco_unitario' => '10,00', 'desconto' => '0'],
            ],
        ])->assertSessionHasNoErrors();

        $orcamento = Orcamento::firstOrFail();
        $this->post(route('orcamento.aprovar', $orcamento))->assertSessionHas('success');

        return [$orcamento->fresh(), Venda::findOrFail($orcamento->fresh()->venda_id)];
    }

    public function test_cancelar_a_venda_do_orcamento_volta_o_orcamento_para_pendente(): void
    {
        [$orcamento, $venda] = $this->orcamentoAprovado();
        $this->assertSame('aprovado', $orcamento->situacao);
        $this->assertEquals(8.0, (float) $this->produto->fresh()->estoque_atual);

        $this->post(route('venda.cancelar', $venda))
            ->assertSessionHas('success', fn (string $mensagem) => str_contains($mensagem, 'O orçamento #'.$orcamento->numero.' voltou'));

        $orcamento->refresh();
        $this->assertSame('pendente', $orcamento->situacao);
        $this->assertNull($orcamento->venda_id);
        $this->assertEquals(10.0, (float) $this->produto->fresh()->estoque_atual);

        $this->post(route('orcamento.aprovar', $orcamento))->assertSessionHas('success');

        $this->assertSame(2, Venda::count());
        $this->assertSame('aprovado', $orcamento->fresh()->situacao);
        $this->assertNotSame($venda->id, $orcamento->fresh()->venda_id);
        $this->assertEquals(8.0, (float) $this->produto->fresh()->estoque_atual);
    }

    public function test_orcamento_que_virou_os_continua_aprovado_quando_a_venda_da_os_e_cancelada(): void
    {
        $this->actingAs($this->admin)->post(route('orcamento.store'), [
            'data_orcamento' => now()->toDateString(),
            'forma_pagamento' => 'Dinheiro',
            'itens' => [
                ['produto_id' => $this->produto->id, 'quantidade' => '1', 'preco_unitario' => '10,00', 'desconto' => '0'],
            ],
        ]);
        $orcamento = Orcamento::firstOrFail();
        $this->post(route('orcamento.gerar-os', $orcamento));
        $os = OrdemServico::where('orcamento_id', $orcamento->id)->firstOrFail();
        $os->update(['equipamento' => 'Celular']);
        $this->post(route('ordem-servico.faturar', $os))->assertSessionHas('success');

        $this->post(route('venda.cancelar', Venda::firstOrFail()))->assertSessionHas('success');

        $this->assertSame('concluida', $os->fresh()->situacao);
        $this->assertSame('aprovado', $orcamento->fresh()->situacao);
    }
}
