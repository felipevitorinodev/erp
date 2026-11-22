<?php

namespace Database\Seeders;

use App\Enums\TipoPessoa;
use App\Models\Empresas\Empresa;
use Carbon\CarbonImmutable;
use Database\Seeders\Desempenho\Carga;
use Database\Seeders\Desempenho\Lote;
use Database\Seeders\Desempenho\Sorteio;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Dados de teste de desempenho: 24 meses de uma loja de varejo com assistência técnica em João Pessoa
 * (na primeira empresa cadastrada) e de uma segunda empresa menor, para os filtros por empresa pesarem.
 *
 * Só acrescenta registros: empresas, usuários (o login atual continua igual), formas de pagamento e o cliente
 * Consumidor já existentes não são alterados. Não gera NF-e/NFC-e nem mexe na numeração fiscal.
 * Não é chamado pelo DatabaseSeeder; rodar à parte:
 *
 *   php artisan db:seed --class=DesempenhoSeeder                 escala 1: ~200 mil vendas na principal, ~16 mil na segunda, ~4 min
 *   SEED_ESCALA=0.05 php artisan db:seed --class=DesempenhoSeeder
 *
 * Usuários criados (vendedores, caixas, gerente, financeiro): e-mail @example.com, senha dev@123.
 */
class DesempenhoSeeder extends Seeder
{
    private const TABELAS = [
        'users', 'funcionarios', 'fornecedores', 'grupos', 'produtos', 'clientes', 'vendas', 'venda_itens', 'movimentacoes_estoque',
        'comissoes', 'contas_receber', 'conta_receber_baixas', 'cobrancas_historico', 'contas_pagar', 'conta_pagar_baixas',
        'orcamentos', 'orcamento_itens', 'ordens_servico', 'ordem_servico_itens', 'pedidos_compra', 'pedido_compra_itens',
        'entradas_estoque', 'entrada_estoque_itens', 'extratos_bancarios', 'transacoes_bancarias',
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Carga de desempenho não roda em produção.');

            return;
        }

        $escala = max(0.01, (float) (env('SEED_ESCALA') ?: 1));
        ini_set('memory_limit', '2G');
        DB::connection()->disableQueryLog();

        if (! Empresa::query()->exists()) {
            $this->call(DatabaseSeeder::class);
        }
        $principal = Empresa::query()->orderBy('id')->firstOrFail();

        if (DB::table('vendas')->where('empresa_id', $principal->id)->exists() && ! env('SEED_DESEMPENHO_REPETIR')) {
            $this->command?->error('A empresa '.$principal->id.' já tem vendas: a carga não foi aplicada de novo (SEED_DESEMPENHO_REPETIR=1 acrescenta outra).');

            return;
        }

        $inicio = microtime(true);
        $hoje = CarbonImmutable::today();
        Sorteio::iniciar(20261120);
        $senha = Hash::make('dev@123');

        $this->command?->info(sprintf('Carga de desempenho: escala %s, de %s a %s.', $escala,
            $hoje->subMonthsNoOverflow(24)->addDay()->format('d/m/Y'), $hoje->format('d/m/Y')));

        Schema::disableForeignKeyConstraints();
        try {
            $lotes = [];
            foreach (self::TABELAS as $tabela) {
                $lotes[$tabela] = new Lote($tabela);
            }

            (new Carga($lotes, (int) $principal->id, $escala, $hoje, true, $senha, $this->command))->executar();

            $segunda = $this->segundaEmpresa($senha);
            (new Carga($lotes, (int) $segunda->id, $escala * 0.08, $hoje, false, $senha, $this->command))->executar();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->table(['Tabela', 'Registros'], array_map(
            fn (Lote $lote) => [$lote->tabela, number_format($lote->total, 0, ',', '.')],
            array_values($lotes),
        ));
        $this->command?->info(sprintf('Concluído em %.1f min.', (microtime(true) - $inicio) / 60));
    }

    /** Segunda empresa (Cabedelo), com perfis padrão criados pelo evento de Empresa e um administrador próprio. */
    private function segundaEmpresa(string $senha): Empresa
    {
        $empresa = Empresa::query()->where('razao_social', 'Comercial Litoral Norte Ltda')->first()
            ?? Empresa::create([
                'razao_social' => 'Comercial Litoral Norte Ltda', 'nome_fantasia' => 'Litoral Norte Variedades',
                'tipo_pessoa' => TipoPessoa::PJ->value, 'cnpj' => Sorteio::cnpj(), 'ie' => '162345678', 'email' => 'contato@example.com',
                'telefone' => '(83) 3228-4410', 'celular' => '(83) 99812-4410', 'cep' => '58100-120', 'logradouro' => 'Avenida Presidente João Pessoa',
                'numero' => '1450', 'bairro' => 'Intermares', 'cidade' => 'Cabedelo', 'uf' => 'PB', 'ativo' => 1,
            ]);

        if (! DB::table('users')->where('email', 'admin.litoral@example.com')->exists()) {
            DB::table('users')->insert([
                'empresa_id' => $empresa->id, 'name' => 'Administrador Litoral Norte', 'email' => 'admin.litoral@example.com',
                'perfil' => 'admin', 'ativo' => true, 'email_verified_at' => now(), 'password' => $senha,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $empresa;
    }
}
