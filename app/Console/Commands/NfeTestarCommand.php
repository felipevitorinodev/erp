<?php

namespace App\Console\Commands;

use App\Models\Empresas\Empresa;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use App\Services\NfeBuilderService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

class NfeTestarCommand extends Command
{
    protected $signature = 'nfe:testar {empresa_id}';

    protected $description = 'Monta e assina um XML de NF-e de teste sem gravar nem enviar à SEFAZ';

    public function handle(): int
    {
        $empresa = Empresa::query()->find($this->argument('empresa_id'));

        if (! $empresa) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $produto = Produto::query()
            ->where('empresa_id', $empresa->id)
            ->where('ativo', true)
            ->with('unidadeMedida')
            ->first();

        if (! $produto) {
            $this->error('Nenhum produto ativo encontrado para montar o teste.');

            return self::FAILURE;
        }

        $numero = str_pad((string) ($empresa->nfe_sequencia ?: 1), 9, '0', STR_PAD_LEFT);

        $venda = new Venda([
            'empresa_id' => $empresa->id,
            'cliente_id' => null,
            'numero' => 'TESTE',
            'data_venda' => now()->toDateString(),
            'subtotal' => 10.00,
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => 10.00,
            'forma_pagamento' => 'Dinheiro',
            'observacoes' => 'Teste artisan nfe:testar',
            'situacao' => 'confirmada',
            'nfe_numero' => $numero,
        ]);

        $item = new VendaItem([
            'produto_id' => $produto->id,
            'produto_nome' => $produto->nome,
            'produto_codigo' => $produto->codigo,
            'quantidade' => 1,
            'preco_unitario' => 10.00,
            'desconto' => 0,
            'total' => 10.00,
        ]);
        $item->setRelation('produto', $produto);

        $venda->setRelation('itens', collect([$item]));
        $venda->setRelation('cliente', null);
        $venda->setRelation('empresa', $empresa);

        try {
            $xml = (new NfeBuilderService($empresa))->buildAndSign($venda);
            $this->info('XML assinado com sucesso ('.strlen($xml).' bytes).');
            $this->line('Número de teste: '.$numero);
            $this->line('Nada foi gravado no banco nem enviado à SEFAZ.');

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Falha ao montar/assinar: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
