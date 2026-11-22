<?php

namespace Tests\Feature;

use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DesempenhoSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Carga de desempenho em escala mínima: só acrescenta, não mexe no login existente, não repete
 * e grava estoque, vendas e financeiro coerentes entre si.
 * Sem RefreshDatabase: o seeder desliga as chaves estrangeiras para gravar em lote, o que o SQLite ignora dentro
 * de transação. O banco de teste é em memória e recriado a cada teste, então basta migrar no início.
 */
class DesempenhoSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    protected function tearDown(): void
    {
        putenv('SEED_ESCALA');
        parent::tearDown();
    }

    public function test_carga_pequena_e_consistente_e_preserva_o_login(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(10, 0));
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('email', 'admin@dev.com')->sole();
        $senhaAntes = $admin->password;
        putenv('SEED_ESCALA=0.01');

        $this->seed(DesempenhoSeeder::class);

        $principal = Empresa::query()->orderBy('id')->first();
        $vendas = DB::table('vendas')->where('empresa_id', $principal->id)->count();
        $this->assertGreaterThan(1000, $vendas);
        $this->assertSame(2, Empresa::query()->count());
        $this->assertGreaterThan(0, DB::table('vendas')->where('empresa_id', '<>', $principal->id)->count());

        $admin->refresh();
        $this->assertSame($senhaAntes, $admin->password);
        $this->assertSame('admin', $admin->perfil);
        $this->assertSame((int) $principal->id, (int) $admin->empresa_id);

        // Estoque: o saldo do produto é o da última movimentação, e nenhuma deixa o saldo negativo.
        $divergentes = DB::table('produtos as p')
            ->join(DB::raw('(select produto_id, max(id) as ult from movimentacoes_estoque group by produto_id) u'), 'u.produto_id', '=', 'p.id')
            ->join('movimentacoes_estoque as m', 'm.id', '=', 'u.ult')
            ->whereRaw('abs(m.estoque_depois - p.estoque_atual) > 0.0005')
            ->count();
        $this->assertSame(0, $divergentes);
        $this->assertSame(0, DB::table('movimentacoes_estoque')->where('estoque_depois', '<', 0)->count());

        // Venda: subtotal é a soma dos itens; a prazo confirmada tem contas somando o total.
        $vendasDivergentes = DB::table('vendas as v')
            ->join(DB::raw('(select venda_id, round(sum(total), 2) as soma from venda_itens group by venda_id) i'), 'i.venda_id', '=', 'v.id')
            ->whereRaw('abs(v.subtotal - i.soma) > 0.01 or abs(v.total - (v.subtotal - v.desconto + v.acrescimo)) > 0.01')
            ->count();
        $this->assertSame(0, $vendasDivergentes);
        $prazoSemConta = DB::table('vendas as v')
            ->join('formas_pagamento as f', 'f.id', '=', 'v.forma_pagamento_id')
            ->leftJoin(DB::raw('(select venda_id, round(sum(valor), 2) as soma from contas_receber group by venda_id) c'), 'c.venda_id', '=', 'v.id')
            ->where('v.situacao', 'confirmada')->where('f.tipo', 'prazo')
            ->whereRaw('abs(coalesce(c.soma, 0) - v.total) > 0.01')
            ->count();
        $this->assertSame(0, $prazoSemConta);

        // Financeiro: valor pago é a soma das baixas, e ninguém pagou no futuro.
        foreach (['contas_receber' => ['conta_receber_baixas', 'conta_receber_id'], 'contas_pagar' => ['conta_pagar_baixas', 'conta_pagar_id']] as $tabela => [$baixas, $chave]) {
            $divergentes = DB::table($tabela.' as c')
                ->leftJoin(DB::raw("(select {$chave}, round(sum(valor), 2) as soma from {$baixas} group by {$chave}) b"), "b.{$chave}", '=', 'c.id')
                ->whereRaw('abs(c.valor_pago - coalesce(b.soma, 0)) > 0.01')
                ->count();
            $this->assertSame(0, $divergentes, $tabela);
            $this->assertSame(0, DB::table($baixas)->where('data_pagamento', '>', '2026-11-20')->count(), $baixas);
        }

        // OS faturada e orçamento aprovado apontam para a venda que geraram.
        $this->assertSame(0, DB::table('ordens_servico')->where('situacao', 'faturada')->whereNull('venda_id')->count());
        $this->assertSame(0, DB::table('orcamentos')->where('situacao', 'aprovado')->whereNull('venda_id')->count());

        // Segunda execução não duplica a carga.
        $this->seed(DesempenhoSeeder::class);
        $this->assertSame($vendas, DB::table('vendas')->where('empresa_id', $principal->id)->count());
        $this->assertSame(2, Empresa::query()->count());
    }
}
