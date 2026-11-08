<?php

namespace App\Repositories\Nfces;

use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use Illuminate\Support\Collection;

class NfceRepository
{
    public function daVenda(int $empresaId, int $vendaId): Collection
    {
        return Nfe::query()
            ->where('empresa_id', $empresaId)
            ->where('venda_id', $vendaId)
            ->orderByDesc('id')
            ->get(['id', 'modelo', 'situacao', 'ambiente', 'serie', 'codigo_status', 'numero']);
    }

    /** A NFC-e pendente que trava a série (com a venda, para dizer onde consultar). */
    public function pendenteDaSerie(int $empresaId, int $ambiente, string $serie): ?Nfe
    {
        return Nfe::query()
            ->with('venda:id,numero')
            ->where('empresa_id', $empresaId)
            ->where('modelo', Nfe::MODELO_NFCE)
            ->where('ambiente', $ambiente)
            ->where('serie', $serie)
            ->where('situacao', 'pendente')
            ->oldest('id')
            ->first();
    }

    /** NFC-e da empresa esperando resposta da SEFAZ, em qualquer série e ambiente. */
    public function pendentes(int $empresaId): Collection
    {
        return Nfe::query()
            ->with('venda:id,numero')
            ->where('empresa_id', $empresaId)
            ->where('modelo', Nfe::MODELO_NFCE)
            ->where('situacao', 'pendente')
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    public function reabrirRejeitada(Nfe $nfe): Nfe
    {
        $nfe->update([
            'situacao' => 'pendente',
            'chave_acesso' => null,
            'xml_assinado' => null,
            'xml_autorizado' => null,
            'protocolo' => null,
            'recibo' => null,
            'codigo_status' => null,
            'motivo_status' => null,
            'data_autorizacao' => null,
        ]);

        return $nfe->fresh();
    }

    public function criarPendente(int $empresaId, int $vendaId, string $numero, string $serie, int $ambiente): Nfe
    {
        return Nfe::query()->create([
            'empresa_id' => $empresaId,
            'venda_id' => $vendaId,
            'modelo' => Nfe::MODELO_NFCE,
            'numero' => $numero,
            'serie' => $serie,
            'situacao' => 'pendente',
            'ambiente' => $ambiente,
        ]);
    }

    public function atualizar(Nfe $nfe, array $dados): Nfe
    {
        $nfe->update($dados);

        return $nfe->fresh();
    }

    public function autorizadaDaVenda(int $empresaId, int $vendaId): ?Nfe
    {
        return Nfe::query()
            ->where('empresa_id', $empresaId)
            ->where('venda_id', $vendaId)
            ->where('modelo', Nfe::MODELO_NFCE)
            ->where('situacao', 'autorizada')
            ->whereNotNull('xml_autorizado')
            ->latest('id')
            ->first();
    }

    public function empresa(int $id): Empresa
    {
        return Empresa::query()->findOrFail($id);
    }

    public function travarVenda(int $vendaId): Venda
    {
        return Venda::query()->whereKey($vendaId)->lockForUpdate()->firstOrFail();
    }

    public function recarregarVenda(Venda $venda): Venda
    {
        return $venda->refresh();
    }

    public function carregarItensFiscais(Venda $venda): Venda
    {
        return $venda->load(['itens.produto.unidadeMedida', 'itens.produto.perfilFiscal', 'cliente']);
    }

    public function atualizarItem(VendaItem $item, array $dados): void
    {
        $item->update($dados);
    }

    public function gravarTpag(Venda $venda, string $tpag): void
    {
        $venda->nfe_tpag = $tpag;
        $venda->save();
    }

    public function produtoComPerfil(int $produtoId): ?Produto
    {
        return Produto::with('perfilFiscal')->find($produtoId);
    }
}
