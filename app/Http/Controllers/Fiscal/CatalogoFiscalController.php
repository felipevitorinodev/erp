<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Fiscal\Cest;
use App\Models\Fiscal\Cfop;
use App\Models\Fiscal\Ncm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogoFiscalController extends Controller
{
    public function cfops(Request $request)
    {
        return $this->listar(Cfop::query(), $request);
    }

    public function ncms(Request $request)
    {
        return $this->listar(Ncm::query(), $request);
    }

    public function cests(Request $request)
    {
        $ncm = preg_replace('/\D/', '', (string) $request->query('ncm', '')) ?? '';
        $query = Cest::query();

        if (strlen($ncm) >= 4) {
            $query->orderByRaw(
                'case when ncm = ? then 0 when ncm like ? then 1 else 2 end',
                [$ncm, substr($ncm, 0, 4).'%']
            );
        }

        return $this->listar($query, $request);
    }

    private function listar(Builder $query, Request $request)
    {
        $consulta = $this->consulta((string) $request->query('q', ''));
        $query->where('ativo', true);
        $this->aplicarTexto($query, $consulta['codigo'], $consulta['texto']);

        return $query->orderBy('codigo')->limit(20)->get()->map(fn ($item) => [
            'codigo' => $item->codigo,
            'descricao' => $item->descricao,
            'rotulo' => $item->codigo.' - '.$item->descricao,
        ])->values();
    }

    /**
     * @return array{codigo: string, texto: string}
     */
    private function consulta(string $q): array
    {
        $q = trim($q);
        if (preg_match('/^(\d{2,8})\s*-/u', $q, $encontrado)) {
            return ['codigo' => $encontrado[1], 'texto' => ''];
        }

        $digitos = preg_replace('/\D/', '', $q) ?? '';

        return ['codigo' => $digitos, 'texto' => $q];
    }

    private function aplicarTexto(Builder $query, string $codigo, string $texto): void
    {
        if ($codigo !== '' && ($texto === '' || $texto === $codigo)) {
            $query->where('codigo', 'like', $codigo.'%');

            return;
        }

        if ($texto === '') {
            return;
        }

        $like = '%'.addcslashes($texto, '%_\\').'%';
        $query->where(function (Builder $filtro) use ($like, $codigo) {
            $filtro->where('descricao', 'like', $like);
            if ($codigo !== '') {
                $filtro->orWhere('codigo', 'like', $codigo.'%');
            }
        });
    }
}
