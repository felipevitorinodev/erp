<?php

namespace App\Repositories\Orcamentos;

use App\Models\Empresas\Empresa;
use App\Models\Orcamentos\Orcamento;
use App\Models\Orcamentos\OrcamentoItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrcamentoRepository
{
    public function index(?Request $request = null)
    {
        $query = Orcamento::with(['cliente'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('numero', 'like', "%{$busca}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%"));
                });
            }
            if ($request->filled('situacao')) {
                $query->where('situacao', $request->situacao);
            }
            if ($request->filled('data_inicio')) {
                $query->whereDate('data_orcamento', '>=', $request->data_inicio);
            }
            if ($request->filled('data_fim')) {
                $query->whereDate('data_orcamento', '<=', $request->data_fim);
            }
        }

        return $query->paginate(20);
    }

    public function findOrFail(int $id): Orcamento
    {
        return Orcamento::with(['cliente', 'itens.produto', 'usuario', 'venda'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    public function proximoNumero(int $empresaId): string
    {
        Empresa::whereKey($empresaId)->lockForUpdate()->first();

        $ultimo = Orcamento::withTrashed()
            ->where('empresa_id', $empresaId)
            ->max(DB::raw('CAST(numero AS UNSIGNED)'));
        $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

        return str_pad($proximo, 6, '0', STR_PAD_LEFT);
    }

    public function store(array $dados, array $itens): Orcamento
    {
        return DB::transaction(function () use ($dados, $itens) {
            $dados['numero'] = $this->proximoNumero((int) $dados['empresa_id']);

            $orcamento = Orcamento::create($dados);
            $this->salvarItens($orcamento, $itens);

            return $orcamento;
        });
    }

    public function update(Orcamento $orcamento, array $dados, array $itens): Orcamento
    {
        return DB::transaction(function () use ($orcamento, $dados, $itens) {
            $orcamento = $this->travarPendente($orcamento);

            $orcamento->update($dados);
            $orcamento->itens()->delete();
            $this->salvarItens($orcamento, $itens);

            return $orcamento;
        });
    }

    public function cancelar(Orcamento $orcamento): Orcamento
    {
        return DB::transaction(function () use ($orcamento) {
            $orcamento = $this->travarPendente($orcamento, ['pendente', 'recusado']);
            $orcamento->update(['situacao' => 'cancelado']);

            return $orcamento;
        });
    }

    public function recusar(Orcamento $orcamento): Orcamento
    {
        return DB::transaction(function () use ($orcamento) {
            $orcamento = $this->travarPendente($orcamento);
            $orcamento->update(['situacao' => 'recusado']);

            return $orcamento;
        });
    }

    /**
     * Relê o orçamento com lock e garante que ainda está numa situação que permite a operação.
     */
    public function travarPendente(Orcamento $orcamento, array $situacoes = ['pendente']): Orcamento
    {
        $atual = Orcamento::whereKey($orcamento->id)->lockForUpdate()->firstOrFail();

        if (!in_array($atual->situacao, $situacoes, true)) {
            throw new \RuntimeException('Este orçamento já foi processado (situação atual: ' . $atual->situacao . ').');
        }

        return $atual;
    }

    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $orcamento = Orcamento::where('empresa_id', auth()->user()->empresa_id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($orcamento->situacao === 'aprovado') {
                throw new \RuntimeException('Orçamento aprovado não pode ser excluído: ele gerou uma venda.');
            }

            return (bool) $orcamento->delete();
        });
    }

    private function salvarItens(Orcamento $orcamento, array $itens): void
    {
        foreach ($itens as $item) {
            OrcamentoItem::create(array_merge($item, ['orcamento_id' => $orcamento->id]));
        }
    }
}
