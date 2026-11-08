<?php

namespace App\Repositories\Nfes;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Funcionarios\Funcionario;
use App\Models\Nfes\Nfe;
use App\Models\Nfes\NfeInutilizacao;
use App\Models\Produtos\Produto;
use App\Models\Vendas\Venda;
use App\Models\Vendas\VendaItem;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class NfeRepository
{
    public function paginar(int $empresaId): LengthAwarePaginator
    {
        return Nfe::query()
            ->where('empresa_id', $empresaId)
            ->where('modelo', Nfe::MODELO_NFE)
            ->with('venda:id,numero')
            ->orderByDesc('id')
            ->paginate(30, [
                'id',
                'numero',
                'serie',
                'venda_id',
                'data_autorizacao',
                'created_at',
                'situacao',
                'ambiente',
            ]);
    }

    public function rascunhos(int $empresaId): Collection
    {
        return Venda::query()
            ->where('empresa_id', $empresaId)
            ->where('nfe_rascunho', true)
            ->where('situacao', 'em_andamento')
            ->with('cliente:id,nome')
            ->orderByDesc('id')
            ->get(['id', 'numero', 'cliente_id', 'total', 'updated_at']);
    }

    public function empresa(int $id): Empresa
    {
        return Empresa::query()->findOrFail($id);
    }

    public function clienteDaEmpresa(int $empresaId, int $clienteId): ?Cliente
    {
        return Cliente::query()
            ->where('empresa_id', $empresaId)
            ->find($clienteId, ['id', 'nome', 'cpf', 'cnpj']);
    }

    public function funcionarioDaEmpresa(int $empresaId, int $funcionarioId): ?Funcionario
    {
        return Funcionario::query()
            ->where('empresa_id', $empresaId)
            ->find($funcionarioId, ['id', 'nome']);
    }

    public function produtosDaEmpresa(int $empresaId, array $ids): Collection
    {
        return Produto::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('id', $ids)
            ->get(['id', 'nome', 'codigo'])
            ->keyBy('id');
    }

    public function carregarRascunho(Venda $venda): void
    {
        $venda->load(['itens', 'cliente', 'funcionario']);
    }

    public function excluirVenda(Venda $venda): void
    {
        $venda->delete();
    }

    public function carregarVenda(Nfe $nfe): void
    {
        $nfe->load('venda');
    }

    public function travarVenda(int $vendaId): Venda
    {
        return Venda::query()->whereKey($vendaId)->lockForUpdate()->firstOrFail();
    }

    public function notasDaVenda(int $empresaId, int $vendaId): Collection
    {
        return Nfe::query()
            ->where('empresa_id', $empresaId)
            ->where('venda_id', $vendaId)
            ->orderByDesc('id')
            ->get(['id', 'modelo', 'situacao', 'ambiente', 'serie', 'codigo_status', 'numero']);
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

        return $nfe;
    }

    public function definirNumeroDaVenda(Venda $venda, string $numero): void
    {
        $venda->update(['nfe_numero' => $numero]);
    }

    public function criarPendente(int $empresaId, int $vendaId, string $numero, string $serie, int $ambiente): Nfe
    {
        return Nfe::query()->create([
            'empresa_id' => $empresaId,
            'venda_id' => $vendaId,
            'modelo' => Nfe::MODELO_NFE,
            'numero' => $numero,
            'serie' => $serie,
            'situacao' => 'pendente',
            'ambiente' => $ambiente,
        ]);
    }

    public function atualizar(Nfe $nfe, array $dados): Nfe
    {
        $nfe->update($dados);

        return $nfe;
    }

    public function recarregarVenda(Venda $venda): Venda
    {
        return $venda->refresh();
    }

    public function carregarParaMontagem(Venda $venda): Venda
    {
        return $venda->loadMissing(['itens.produto.unidadeMedida', 'itens.produto.perfilFiscal', 'cliente']);
    }

    public function carregarItensFiscais(Venda $venda): Venda
    {
        return $venda->load(['itens.produto.perfilFiscal', 'cliente']);
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

    public function vendaTemNotaBloqueandoLimpeza(int $empresaId, int $vendaId): bool
    {
        return Nfe::query()
            ->where('empresa_id', $empresaId)
            ->where('venda_id', $vendaId)
            ->whereIn('situacao', ['autorizada', 'pendente', 'cancelada'])
            ->exists();
    }

    public function vendaDaEmpresa(int $empresaId, int $vendaId): Venda
    {
        return Venda::query()->where('empresa_id', $empresaId)->findOrFail($vendaId);
    }

    public function limparCodigosDaVenda(Venda $venda): void
    {
        $venda->itens()->update([
            'cfop' => null,
            'csosn' => null,
            'cst_pis' => null,
            'cst_cofins' => null,
        ]);
        $venda->update(['nfe_tpag' => null]);
    }

    /** NF-e e NFC-e juntas: a tela mostra o modelo de cada pedido. */
    public function inutilizacoes(int $empresaId): Collection
    {
        return NfeInutilizacao::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('modelo', [Nfe::MODELO_NFE, Nfe::MODELO_NFCE])
            ->orderByDesc('id')
            ->limit(20)
            ->get();
    }

    public function faixaOcupada(int $empresaId, int $ambiente, string $serie, int $inicio, int $fim, int $modelo = Nfe::MODELO_NFE): bool
    {
        return Nfe::withTrashed()
            ->where('empresa_id', $empresaId)
            ->where('modelo', $modelo)
            ->where('ambiente', $ambiente)
            ->where('serie', $serie)
            ->whereBetween('numero', [
                str_pad((string) $inicio, 9, '0', STR_PAD_LEFT),
                str_pad((string) $fim, 9, '0', STR_PAD_LEFT),
            ])
            ->exists();
    }

    public function criarInutilizacao(array $dados): NfeInutilizacao
    {
        return NfeInutilizacao::query()->create($dados);
    }
}
