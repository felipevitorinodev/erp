<?php

namespace App\Repositories\Produtos;

use App\Models\Empresas\Empresa;
use App\Models\Estoque\MovimentacaoEstoque;
use App\Models\Produtos\Produto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProdutoRepository
{
    public function index(?Request $request = null)
    {
        $query = Produto::with(['grupos:id,nome', 'unidadeMedida:id,sigla'])
            ->where('empresa_id', auth()->user()->empresa_id)
            ->orderBy('nome');

        if ($request) {
            if ($request->filled('busca')) {
                $busca = $request->busca;
                $query->where(function ($q) use ($busca) {
                    $q->where('nome', 'like', "%{$busca}%")
                        ->orWhere('codigo', 'like', "%{$busca}%")
                        ->orWhere('codigo_barras', 'like', "%{$busca}%");
                });
            }
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }
            if ($request->filled('grupo_id')) {
                $query->where('grupo_id', $request->grupo_id);
            }
        }

        return $query->paginate(20, [
            'id',
            'codigo',
            'grupo_id',
            'unidade_medida_id',
            'nome',
            'preco_venda',
            'estoque_atual',
            'estoque_minimo',
            'controla_estoque',
            'ativo',
        ]);
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data['empresa_id'] = auth()->user()->empresa_id;
            if (trim((string) ($data['codigo'] ?? '')) === '') {
                $data['codigo'] = $this->proximoCodigo((int) $data['empresa_id']);
            }

            $estoqueInicial = (float) ($data['estoque_atual'] ?? 0);
            if (empty($data['controla_estoque'])) {
                $estoqueInicial = 0;
                $data['estoque_atual'] = 0;
            }

            $produto = Produto::create($data);

            if (! empty($data['controla_estoque']) && $estoqueInicial > 0) {
                MovimentacaoEstoque::create([
                    'empresa_id' => $produto->empresa_id,
                    'produto_id' => $produto->id,
                    'user_id' => auth()->id(),
                    'tipo' => 'ajuste',
                    'quantidade' => $estoqueInicial,
                    'estoque_antes' => 0,
                    'estoque_depois' => $estoqueInicial,
                    'motivo' => 'Estoque inicial',
                    'origem' => 'ajuste_manual',
                    'origem_id' => $produto->id,
                ]);
            }

            return $produto;
        });
    }

    public function update(Produto $produto, array $data)
    {
        unset($data['empresa_id'], $data['estoque_atual']);

        return DB::transaction(function () use ($produto, $data) {
            if (trim((string) ($produto->codigo ?? '')) === '') {
                $data['codigo'] = $this->proximoCodigo((int) $produto->empresa_id);
            } else {
                unset($data['codigo']);
            }

            $produto->update($data);

            return $produto;
        });
    }

    /**
     * Dentro de uma transação, a trava na empresa serializa a numeração (mesmo padrão de vendas e orçamentos).
     * Os excluídos contam para o código de um produto que já saiu em venda não ser reaproveitado.
     */
    public function proximoCodigo(int $empresaId): string
    {
        Empresa::whereKey($empresaId)->lockForUpdate()->first(['id']);

        $ultimo = Produto::withTrashed()
            ->where('empresa_id', $empresaId)
            ->whereRaw($this->codigoSoDigitos())
            ->max(DB::raw('CAST(codigo AS UNSIGNED)'));

        $proximo = $ultimo ? ((int) $ultimo) + 1 : 1;

        return str_pad((string) $proximo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Código formado só por dígitos. O SQLite (testes) não tem REGEXP.
     */
    private function codigoSoDigitos(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "codigo <> '' AND codigo NOT GLOB '*[^0-9]*'"
            : "codigo REGEXP '^[0-9]+$'";
    }

    public function destroy($id)
    {
        $produto = Produto::where('empresa_id', auth()->user()->empresa_id)->findOrFail($id);
        $produto->delete();

        return $produto;
    }
}
