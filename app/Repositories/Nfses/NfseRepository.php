<?php

namespace App\Repositories\Nfses;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Nfses\Nfse;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class NfseRepository
{
    public function paginar(int $empresaId, array $filtros = []): LengthAwarePaginator
    {
        $situacao = (string) ($filtros['situacao'] ?? '');

        return Nfse::query()
            ->where('empresa_id', $empresaId)
            ->when($situacao !== '', fn ($query) => $query->where('situacao', $situacao))
            ->with(['venda:id,numero,cliente_id', 'venda.cliente:id,nome'])
            ->orderByDesc('id')
            ->paginate(30, [
                'id',
                'venda_id',
                'serie',
                'numero',
                'numero_nfse',
                'situacao',
                'ambiente',
                'valor_liquido',
                'data_competencia',
                'data_autorizacao',
                'created_at',
            ])
            ->withQueryString();
    }

    public function empresa(int $id): Empresa
    {
        return Empresa::query()->findOrFail($id);
    }

    public function travarVenda(int $vendaId): Venda
    {
        return Venda::query()->whereKey($vendaId)->lockForUpdate()->firstOrFail();
    }

    public function carregarVenda(Venda $venda): Venda
    {
        return $venda->loadMissing(['itens.produto', 'cliente']);
    }

    /**
     * @return Collection<int, Nfse>
     */
    public function notasDaVenda(int $empresaId, int $vendaId): Collection
    {
        return Nfse::query()
            ->where('empresa_id', $empresaId)
            ->where('venda_id', $vendaId)
            ->orderByDesc('id')
            ->get();
    }

    public function criar(array $dados): Nfse
    {
        return Nfse::query()->create($dados);
    }

    public function atualizar(Nfse $nfse, array $dados): Nfse
    {
        $nfse->fill($dados)->save();

        return $nfse;
    }

    public function travar(int $id): Nfse
    {
        return Nfse::query()->whereKey($id)->lockForUpdate()->firstOrFail();
    }

    /**
     * @param  list<int>  $ids
     */
    public function gravarCodigosNosProdutos(int $empresaId, array $ids, array $codigos): int
    {
        if ($ids === []) {
            return 0;
        }

        return Produto::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->update($codigos);
    }

    public function gravarRetencaoNoCliente(int $empresaId, int $clienteId, bool $retido): void
    {
        Cliente::query()
            ->where('empresa_id', $empresaId)
            ->whereKey($clienteId)
            ->update(['nfse_iss_retido' => $retido]);
    }

    public function gravarConvenio(Empresa $empresa, ?string $situacao): void
    {
        $empresa->forceFill([
            'nfse_convenio_situacao' => $situacao,
            'nfse_convenio_consultado_em' => now(),
        ])->save();
    }
}
