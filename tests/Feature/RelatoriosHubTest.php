<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\User;
use App\Support\CatalogoRelatorios;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RelatoriosHubTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create([
            'razao_social' => 'Empresa A',
            'cnpj' => '11222333000181',
        ]);
    }

    private function usuario(string $perfil): User
    {
        return User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => $perfil,
            'ativo' => true,
        ]);
    }

    private function perfil(string $slug, array $permissoes): void
    {
        Perfil::firstOrCreate(
            ['empresa_id' => $this->empresa->id, 'slug' => $slug],
            ['nome' => $slug, 'permissoes' => $permissoes, 'sistema' => false, 'ativo' => true],
        );
    }

    public function test_catalogo_aponta_somente_para_rotas_e_categorias_existentes(): void
    {
        $slugs = [];

        foreach (CatalogoRelatorios::relatorios() as $relatorio) {
            $this->assertNotContains($relatorio['slug'], $slugs);
            $slugs[] = $relatorio['slug'];
            $this->assertArrayHasKey($relatorio['categoria'], CatalogoRelatorios::categorias());
            $this->assertTrue(Route::has($relatorio['rota']));
            $this->assertContains('pdf', $relatorio['exportacoes']);
            $this->assertContains('csv', $relatorio['exportacoes']);
            $this->assertTrue($relatorio['ativo']);
        }

        $this->assertSame([
            'vendas',
            'produtos-mais-vendidos',
            'vendas-por-vendedor',
            'vendas-por-dia-semana',
            'ranking-clientes',
            'estoque',
            'contas-receber',
            'contas-pagar',
            'inadimplencia-receber',
            'inadimplencia-pagar',
            'clientes-com-debito',
        ], $slugs);
        $this->assertSame(366, CatalogoRelatorios::periodoMaximoDias());
    }

    public function test_relatorio_inativo_ou_sem_todas_as_permissoes_fica_oculto(): void
    {
        $this->perfil('analista', ['relatorios']);
        $analista = $this->usuario('analista');

        $this->assertFalse(CatalogoRelatorios::usuarioPode($analista, [
            'ativo' => false,
            'permissoes' => ['relatorios'],
        ]));

        $this->assertFalse(CatalogoRelatorios::usuarioPode($analista, [
            'ativo' => true,
            'permissoes' => ['relatorios', 'financeiro'],
        ]));

        $this->assertTrue(CatalogoRelatorios::usuarioPode($analista, [
            'ativo' => true,
            'permissoes' => ['relatorios'],
        ]));
    }

    public function test_hub_lista_relatorios_permitidos_e_esconde_os_demais(): void
    {
        $this->perfil('analista', ['relatorios']);
        $this->perfil('gestor', ['relatorios', 'financeiro']);

        $analista = $this->usuario('analista');
        $gestor = $this->usuario('gestor');

        $this->actingAs($analista)
            ->get(route('relatorios.index'))
            ->assertOk()
            ->assertSee('Buscar relatório')
            ->assertSee('relatorios-hub.js', false)
            ->assertSeeInOrder([
                'Vendas por período',
                'Produtos mais vendidos',
                'Vendas por vendedor',
                'Vendas por dia da semana',
                'Ranking de clientes',
                'Posição de estoque',
            ])
            ->assertDontSee('Contas a receber')
            ->assertDontSee('Contas a pagar')
            ->assertDontSee('Inadimplência')
            ->assertDontSee('Clientes com débito')
            ->assertDontSee('Produtos Mais Vendidos')
            ->assertDontSee('sidebar__group-label">Relatórios', false);

        $this->actingAs($gestor)
            ->get(route('relatorios.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Vendas por período',
                'Produtos mais vendidos',
                'Vendas por vendedor',
                'Vendas por dia da semana',
                'Ranking de clientes',
                'Posição de estoque',
                'Contas a receber',
                'Contas a pagar',
                'Inadimplência a receber',
                'Inadimplência a pagar',
                'Clientes com débito',
            ]);
    }

    public function test_item_do_menu_fica_ativo_no_hub_e_em_qualquer_relatorio(): void
    {
        $admin = $this->usuario('admin');
        $hub = preg_quote(route('relatorios.index'), '/');

        $noHub = $this->actingAs($admin)->get(route('relatorios.index'))->assertOk()->getContent();
        $naVenda = $this->actingAs($admin)->get(route('relatorio.vendas'))->assertOk()->getContent();
        $noProduto = $this->actingAs($admin)->get(route('produto.index'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/href="'.$hub.'"[^>]*sidebar__link--active/', $noHub);
        $this->assertMatchesRegularExpression('/href="'.$hub.'"[^>]*sidebar__link--active/', $naVenda);
        $this->assertDoesNotMatchRegularExpression('/href="'.$hub.'"[^>]*sidebar__link--active/', $noProduto);
        $this->assertDoesNotMatchRegularExpression('/sidebar__group-label\s*>\s*Relatórios/', $naVenda);
        $this->assertStringContainsString('Vendas por período', $naVenda);
    }

    public function test_atalho_da_home_abre_o_hub_e_os_atalhos_numericos_continuam_no_relatorio(): void
    {
        $this->perfil('analista', ['relatorios']);

        $this->actingAs($this->usuario('analista'))
            ->get('/')
            ->assertOk()
            ->assertSee('href="'.route('relatorios.index').'"', false)
            ->assertSee(route('relatorio.vendas', [
                'data_inicio' => date('Y-m-d'),
                'data_fim' => date('Y-m-d'),
            ]));
    }

    public function test_usuario_sem_relatorio_acessivel_recebe_403_e_nao_ve_o_menu(): void
    {
        $vendedor = $this->usuario('vendedor');

        $this->actingAs($vendedor)
            ->get(route('relatorios.index'))
            ->assertForbidden();

        $this->actingAs($vendedor)
            ->get('/')
            ->assertOk()
            ->assertDontSee('href="'.route('relatorios.index').'"', false);
    }

    public function test_visitante_e_redirecionado_ao_login(): void
    {
        $this->get(route('relatorios.index'))
            ->assertRedirect(route('login'));
    }

    public function test_rotas_antigas_dos_relatorios_continuam_respondendo(): void
    {
        $admin = $this->usuario('admin');

        foreach ([
            'relatorio.vendas',
            'relatorio.produtos-mais-vendidos',
            'relatorio.estoque',
            'relatorio.contas-receber',
            'relatorio.contas-pagar',
        ] as $rota) {
            $this->actingAs($admin)->get(route($rota))->assertOk();
        }
    }
}
