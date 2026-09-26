<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EmpresaSeeder::class,
            AuthSeeder::class,
            ClienteConsumidorSeeder::class,
            GrupoSeeder::class,
            UnidadeMedidaSeeder::class,
            FormaPagamentoSeeder::class,
            DemoDadosSeeder::class,
        ]);
    }
}
