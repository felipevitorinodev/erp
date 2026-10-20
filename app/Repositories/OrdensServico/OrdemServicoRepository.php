<?php

namespace App\Repositories\OrdensServico;

use App\Models\Empresas\Empresa;
use App\Models\OrdensServico\OrdemServico;
use App\Models\OrdensServico\OrdemServicoItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdemServicoRepository
{
    public function index(?Request $request = null)
    {
        $query = OrdemServico::with(['cliente', 'tecnico'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderByDesc('id');

        if ($request) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('numero', 'like', "%{$busca}%")
                        ->orWhere('equipamento', 'like', "%{$busca}%")
                        ->orWhere('numero_serie', 'like', "%{$busca}%")
                        ->orWhereHas('cliente', fn ($c) => $c->where('nome', 'like', "%{$busca}%"));
                });
            }
            if ($request->filled('situacao')) {
                $query->where('situacao', $request->situacao);
            }
            if ($request->filled('funcionario_id')) {
                $query->where('funcionario_id', $request->funcionario_id);
            }
            if ($request->filled('data_inicio')) {
                $query->whereDate('data_abertura', '>=', $request->data_inicio);
            }
            if ($request->filled('data_fim')) {
                $query->whereDate('data_abertura', '<=', $request->data_fim);
            }
        }

        return $query->paginate(20);
    }

    public function proximoNumero(int $empresaId): string
    {
        Empresa::whereKey($empresaId)->lockForUpdate()->first();

        $ultimo = OrdemServico::withTrashed()
            ->where('empresa_id', $empresaId)
            ->max(DB::raw('CAST(numero AS UNSIGNED)'));
        $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

        return str_pad($proximo, 6, '0', STR_PAD_LEFT);
    }

    public function store(array $dados, array $itens): OrdemServico
    {
        return DB::transaction(function () use ($dados, $itens) {
            $dados['numero'] = $this->proximoNumero((int) $dados['empresa_id']);

            $os = OrdemServico::create($dados);
            $this->salvarItens($os, $itens);

            return $os;
        });
    }

    public function update(OrdemServico $os, array $dados, array $itens): OrdemServico
    {
        return DB::transaction(function () use ($os, $dados, $itens) {
            $os = $this->travarAberta($os);

            $os->update($dados);
            $os->itens()->delete();
            $this->salvarItens($os, $itens);

            return $os;
        });
    }

    public function alterarSituacao(OrdemServico $os, string $situacao): OrdemServico
    {
        return DB::transaction(function () use ($os, $situacao) {
            $os = $this->travarAberta($os);

            $dados = ['situacao' => $situacao];
            if ($situacao === 'concluida' && ! $os->data_conclusao) {
                $dados['data_conclusao'] = now()->toDateString();
            } elseif ($situacao !== 'concluida') {
                $dados['data_conclusao'] = null;
            }

            $os->update($dados);

            return $os;
        });
    }

    public function cancelar(OrdemServico $os): OrdemServico
    {
        return DB::transaction(function () use ($os) {
            $os = $this->travarAberta($os);
            $os->update(['situacao' => 'cancelada']);

            return $os;
        });
    }

    /**
     * Relê a OS com lock e garante que ela ainda não foi faturada nem cancelada.
     */
    public function travarAberta(OrdemServico $os): OrdemServico
    {
        $atual = OrdemServico::whereKey($os->id)->lockForUpdate()->firstOrFail();

        if (! $atual->estaAberta()) {
            throw new \RuntimeException('Esta ordem de serviço já foi encerrada (situação atual: ' . $atual->situacaoLabel() . ').');
        }

        return $atual;
    }

    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $os = OrdemServico::where('empresa_id', auth()->user()->empresa_id)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($os->situacao === 'faturada') {
                throw new \RuntimeException('Ordem de serviço faturada não pode ser excluída: ela gerou uma venda.');
            }

            return (bool) $os->delete();
        });
    }

    public function salvarItens(OrdemServico $os, array $itens): void
    {
        foreach ($itens as $item) {
            OrdemServicoItem::create(array_merge($item, ['ordem_servico_id' => $os->id]));
        }
    }
}
