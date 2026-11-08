<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class TelasErroEEstadoVazioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/_teste/erro/{codigo}', function (int $codigo) {
            abort($codigo, '', $codigo === 429 ? ['Retry-After' => 30] : []);
        });
        Route::middleware('web')->get('/_teste/falha', function () {
            throw new RuntimeException('SQLSTATE senha=segredo123 cpf 12345678900');
        });
    }

    public function test_pagina_inexistente_mostra_tela_amigavel(): void
    {
        $this->get('/pagina-que-nao-existe')
            ->assertNotFound()
            ->assertSee('Erro 404')
            ->assertSee('Página não encontrada')
            ->assertSee('ilustracao--nao-encontrado', false)
            ->assertSee('data-erro-voltar', false)
            ->assertSee('Ir para o início');
    }

    public function test_api_continua_respondendo_json(): void
    {
        $this->getJson('/api/rota-que-nao-existe')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_cada_erro_comum_tem_tela_propria(): void
    {
        $esperado = [
            401 => 'Entre para continuar',
            403 => 'Você não tem acesso a esta tela',
            413 => 'Arquivo grande demais',
            419 => 'A página expirou',
            503 => 'Sistema em manutenção',
            418 => 'Não foi possível concluir',
        ];

        foreach ($esperado as $codigo => $titulo) {
            $this->get('/_teste/erro/'.$codigo)
                ->assertStatus($codigo)
                ->assertSee('Erro '.$codigo)
                ->assertSee($titulo);
        }
    }

    public function test_muitas_tentativas_usa_o_tempo_do_cabecalho(): void
    {
        $this->get('/_teste/erro/429')
            ->assertStatus(429)
            ->assertSee('Muitas tentativas seguidas')
            ->assertSee('data-contagem="30"', false)
            ->assertSee('id="erro-tentar"', false);
    }

    public function test_manutencao_recarrega_sozinha(): void
    {
        $this->get('/_teste/erro/503')
            ->assertSee('data-contagem-acao="recarregar"', false);
    }

    public function test_erro_interno_nao_mostra_detalhe_tecnico(): void
    {
        config(['app.debug' => false]);

        $this->get('/_teste/falha')
            ->assertStatus(500)
            ->assertSee('Algo deu errado do nosso lado')
            ->assertSee('Horário do erro')
            ->assertDontSee('segredo123')
            ->assertDontSee('12345678900')
            ->assertDontSee('SQLSTATE');
    }

    public function test_lista_vazia_oferece_o_primeiro_cadastro(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cliente.index'))
            ->assertOk()
            ->assertSee('Nenhum cliente cadastrado')
            ->assertSee('+ Novo cliente')
            ->assertDontSee('Limpar filtros');
    }

    public function test_lista_filtrada_sem_resultado_oferece_limpar_filtros(): void
    {
        $this->actingAs($this->admin())
            ->get(route('cliente.index', ['busca' => 'ninguem-com-este-nome']))
            ->assertOk()
            ->assertSee('Nada encontrado com esses filtros')
            ->assertSee('Limpar filtros')
            ->assertSee('ilustracao--sem-resultado', false);
    }

    private function admin(): User
    {
        $empresa = Empresa::create([
            'razao_social' => 'Empresa Teste',
            'nome_fantasia' => 'Empresa Teste',
            'cnpj' => '11222333000181',
            'ativo' => true,
        ]);

        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]);
    }
}
