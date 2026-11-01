<?php

namespace App\Services\Relatorios;

use App\Models\Usuarios\User;
use App\Repositories\Relatorios\ContaRelatorioRepository;
use App\Repositories\Relatorios\InadimplenciaRelatorioRepository;
use App\Support\CatalogoRelatorios;

class RelatorioRegistro
{
    public function obter(string $slug): ConsultaRelatorio
    {
        $item = collect(CatalogoRelatorios::relatorios())->firstWhere('slug', $slug);

        if (! $item) {
            abort(404);
        }

        $usuario = auth()->user();
        if (! $usuario instanceof User || ! CatalogoRelatorios::usuarioPode($usuario, $item)) {
            abort(403, 'Acesso não permitido.');
        }

        return match ($slug) {
            'vendas' => app(VendaRelatorioService::class),
            'produtos-mais-vendidos' => app(ProdutoMaisVendidoRelatorioService::class),
            'estoque' => app(EstoqueRelatorioService::class),
            'contas-receber' => new ContaRelatorioService(app(ContaRelatorioRepository::class), 'receber'),
            'contas-pagar' => new ContaRelatorioService(app(ContaRelatorioRepository::class), 'pagar'),
            'vendas-por-vendedor' => app(VendedorRelatorioService::class),
            'vendas-por-dia-semana' => app(DiaSemanaRelatorioService::class),
            'ranking-clientes' => app(RankingClienteRelatorioService::class),
            'clientes-com-debito' => app(ClienteDebitoRelatorioService::class),
            'inadimplencia-receber' => new InadimplenciaRelatorioService(app(InadimplenciaRelatorioRepository::class), 'receber'),
            'inadimplencia-pagar' => new InadimplenciaRelatorioService(app(InadimplenciaRelatorioRepository::class), 'pagar'),
            default => abort(404),
        };
    }
}
