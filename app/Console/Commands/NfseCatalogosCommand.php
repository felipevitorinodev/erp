<?php

namespace App\Console\Commands;

use App\Support\CatalogoNfse;
use Illuminate\Console\Command;

class NfseCatalogosCommand extends Command
{
    protected $signature = 'nfse:catalogos';

    protected $description = 'Atualiza as tabelas oficiais da NFS-e (serviços, NBS, cClassTrib, indicadores, correlação e municípios) a partir de database/data/nfse';

    public function handle(): int
    {
        foreach (CatalogoNfse::sincronizar() as $tabela => $linhas) {
            $this->line(str_pad($tabela, 28).$linhas.' linhas');
        }

        $this->info('Tabelas da NFS-e atualizadas.');

        return self::SUCCESS;
    }
}
