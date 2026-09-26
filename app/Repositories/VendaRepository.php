<?php

namespace App\Repositories;

use App\Models\Comissao;
use App\Models\ContaReceber;
use App\Models\Funcionario;
use App\Models\MovimentacaoEstoque;
use App\Models\Produto;
use App\Models\Venda;
use App\Models\VendaItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VendaRepository
{
    public function __construct(
        protected ContaReceberRepository $contaReceberRepository
    ) {}

    public function index(?Request $request = null)
    {
        $query = Venda::with(['cliente', 'funcionario'])
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
                $query->whereDate('data_venda', '>=', $request->data_inicio);
            }
            if ($request->filled('data_fim')) {
                $query->whereDate('data_venda', '<=', $request->data_fim);
            }
        }

        return $query->paginate(20);
    }

    public function findOrFail(int $id): Venda
    {
        return Venda::with(['cliente', 'funcionario', 'itens.produto', 'usuario'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->findOrFail($id);
    }

    public function proximoNumero(int $empresaId): string
    {
        $ultimo = Venda::where('empresa_id', $empresaId)
            ->max('numero');

        $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

        return str_pad($proximo, 6, '0', STR_PAD_LEFT);
    }

    public function store(array $dados, array $itens): Venda
    {
        return DB::transaction(function () use ($dados, $itens) {
            $venda = Venda::create($dados);
            $this->salvarItens($venda, $itens);
            return $venda;
        });
    }

    public function update(Venda $venda, array $dados, array $itens): Venda
    {
        return DB::transaction(function () use ($venda, $dados, $itens) {
            if ($venda->situacao === 'confirmada') {
                $this->estornarEstoque($venda);
            }

            $venda->update($dados);

            $venda->itens()->delete();
            $this->salvarItens($venda, $itens);

            $venda = $venda->fresh(['itens', 'funcionario']);

            if ($venda->situacao === 'confirmada') {
                $this->aplicarEstoque($venda);
                $this->sincronizarContaReceberAberta($venda);
                $this->sincronizarComissaoPendente($venda);
            }

            return $venda;
        });
    }

    public function confirmar(Venda $venda): Venda
    {
        return DB::transaction(function () use ($venda) {
            $venda = Venda::where('id', $venda->id)->lockForUpdate()->firstOrFail();

            if ($venda->situacao !== 'em_andamento') {
                throw new \Exception('Somente vendas em andamento podem ser confirmadas.');
            }

            $venda->update(['situacao' => 'confirmada']);
            $this->aplicarEstoque($venda->load('itens'));
            $this->contaReceberRepository->gerarDaVendaConfirmada($venda);
            $this->gerarComissaoDaVenda($venda);

            return $venda;
        });
    }

    public function cancelar(Venda $venda, string $situacao = 'cancelada'): Venda
    {
        return DB::transaction(function () use ($venda, $situacao) {
            if ($venda->situacao === 'confirmada') {
                $temRecebido = ContaReceber::where('venda_id', $venda->id)
                    ->whereIn('situacao', ['paga', 'parcial'])
                    ->exists();

                if ($temRecebido) {
                    throw new \Exception('Não é possível cancelar: existe conta a receber já paga ou parcial vinculada a esta venda.');
                }

                $temComissaoPaga = Comissao::where('venda_id', $venda->id)
                    ->where('situacao', 'paga')
                    ->exists();

                if ($temComissaoPaga) {
                    throw new \Exception('Não é possível cancelar: existe comissão já paga vinculada a esta venda.');
                }

                $this->estornarEstoque($venda);

                ContaReceber::where('venda_id', $venda->id)
                    ->whereIn('situacao', ['aberta', 'parcial'])
                    ->update(['situacao' => 'cancelada']);
            }

            Comissao::where('venda_id', $venda->id)
                ->where('situacao', 'pendente')
                ->delete();

            $venda->update(['situacao' => $situacao]);

            return $venda;
        });
    }

    public function destroy(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $venda = Venda::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);

            if ($venda->situacao === 'confirmada') {
                throw new \Exception('Venda confirmada não pode ser excluída. Cancele-a primeiro.');
            }

            return (bool) $venda->delete();
        });
    }

    private function gerarComissaoDaVenda(Venda $venda): void
    {
        if (!$venda->funcionario_id) {
            return;
        }

        if (Comissao::where('venda_id', $venda->id)->exists()) {
            return;
        }

        $funcionario = Funcionario::where('id', $venda->funcionario_id)
            ->where('empresa_id', $venda->empresa_id)
            ->first();

        if (!$funcionario) {
            return;
        }

        $percentual = (float) $funcionario->percentual_comissao;
        if ($percentual <= 0) {
            return;
        }

        $valor = round((float) $venda->total * ($percentual / 100), 2);

        Comissao::create([
            'empresa_id'     => $venda->empresa_id,
            'venda_id'       => $venda->id,
            'funcionario_id' => $funcionario->id,
            'percentual'     => $percentual,
            'valor'          => $valor,
            'situacao'       => 'pendente',
        ]);
    }

    /**
     * Atualiza conta a receber aberta vinculada à venda (valor/cliente/forma).
     * Não altera contas já pagas ou parciais.
     */
    private function sincronizarContaReceberAberta(Venda $venda): void
    {
        $conta = ContaReceber::where('venda_id', $venda->id)
            ->where('situacao', 'aberta')
            ->first();

        if (!$conta) {
            return;
        }

        $conta->update([
            'cliente_id'      => $venda->cliente_id,
            'valor'           => $venda->total,
            'forma_pagamento' => $venda->forma_pagamento,
            'descricao'       => 'Venda #' . $venda->numero . ' — ' . ($venda->forma_pagamento ?: 'Conta a receber'),
        ]);
    }

    /**
     * Recalcula comissão pendente após edição de venda confirmada.
     * Comissões já pagas não são alteradas.
     */
    private function sincronizarComissaoPendente(Venda $venda): void
    {
        $comissao = Comissao::where('venda_id', $venda->id)->first();

        if ($comissao && $comissao->situacao === 'paga') {
            return;
        }

        if (!$venda->funcionario_id) {
            if ($comissao && $comissao->situacao === 'pendente') {
                $comissao->delete();
            }
            return;
        }

        $funcionario = Funcionario::where('id', $venda->funcionario_id)
            ->where('empresa_id', $venda->empresa_id)
            ->first();

        if (!$funcionario || (float) $funcionario->percentual_comissao <= 0) {
            if ($comissao && $comissao->situacao === 'pendente') {
                $comissao->delete();
            }
            return;
        }

        $percentual = (float) $funcionario->percentual_comissao;
        $valor = round((float) $venda->total * ($percentual / 100), 2);

        if ($comissao) {
            $comissao->update([
                'funcionario_id' => $funcionario->id,
                'percentual'     => $percentual,
                'valor'          => $valor,
            ]);
            return;
        }

        Comissao::create([
            'empresa_id'     => $venda->empresa_id,
            'venda_id'       => $venda->id,
            'funcionario_id' => $funcionario->id,
            'percentual'     => $percentual,
            'valor'          => $valor,
            'situacao'       => 'pendente',
        ]);
    }

    private function salvarItens(Venda $venda, array $itens): void
    {
        foreach ($itens as $item) {
            VendaItem::create(array_merge($item, ['venda_id' => $venda->id]));
        }
    }

    private function aplicarEstoque(Venda $venda): void
    {
        foreach ($venda->itens as $item) {
            if (!$item->produto_id) {
                continue;
            }

            $produto = Produto::where('id', $item->produto_id)
                ->where('empresa_id', $venda->empresa_id)
                ->where('controla_estoque', true)
                ->lockForUpdate()
                ->first();

            if (!$produto) {
                continue;
            }

            $antes = (float) $produto->estoque_atual;
            $depois = $antes - (float) $item->quantidade;

            $produto->decrement('estoque_atual', $item->quantidade);

            MovimentacaoEstoque::create([
                'empresa_id'     => $venda->empresa_id,
                'produto_id'     => $produto->id,
                'user_id'        => auth()->id(),
                'tipo'           => 'saida',
                'quantidade'     => $item->quantidade,
                'estoque_antes'  => $antes,
                'estoque_depois' => $depois,
                'motivo'         => 'Venda #' . $venda->numero,
                'origem'         => 'venda',
                'origem_id'      => $venda->id,
            ]);
        }
    }

    private function estornarEstoque(Venda $venda): void
    {
        foreach ($venda->itens as $item) {
            if (!$item->produto_id) {
                continue;
            }

            $produto = Produto::where('id', $item->produto_id)
                ->where('empresa_id', $venda->empresa_id)
                ->where('controla_estoque', true)
                ->lockForUpdate()
                ->first();

            if (!$produto) {
                continue;
            }

            $antes = (float) $produto->estoque_atual;
            $depois = $antes + (float) $item->quantidade;

            $produto->increment('estoque_atual', $item->quantidade);

            MovimentacaoEstoque::create([
                'empresa_id'     => $venda->empresa_id,
                'produto_id'     => $produto->id,
                'user_id'        => auth()->id(),
                'tipo'           => 'entrada',
                'quantidade'     => $item->quantidade,
                'estoque_antes'  => $antes,
                'estoque_depois' => $depois,
                'motivo'         => 'Estorno venda #' . $venda->numero,
                'origem'         => 'venda',
                'origem_id'      => $venda->id,
            ]);
        }
    }
}
