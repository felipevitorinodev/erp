<?php

namespace App\Http\Controllers\Fiscal;

use App\Http\Controllers\Controller;
use App\Models\Fiscal\Municipio;
use App\Models\Fiscal\NfseClassificacao;
use App\Models\Fiscal\NfseCorrelacao;
use App\Models\Fiscal\NfseIndicadorOperacao;
use App\Models\Fiscal\NfseNbs;
use App\Models\Fiscal\NfseServico;
use App\Models\Produtos\Produto;
use App\Services\Nfses\NfseCodigos;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Busca (autocompletar) das tabelas da NFS-e. Os códigos já usados pela empresa nos produtos aparecem primeiro,
 * e o NBS, o indicador e a classificação vêm ordenados pela correlação oficial do serviço escolhido.
 */
class CatalogoNfseController extends Controller
{
    public function servicos(Request $request)
    {
        $usados = $this->usadosNaEmpresa('nfse_c_trib_nac');
        $query = NfseServico::query()->where('ativo', true);
        $this->filtrar($query, (string) $request->query('q', ''), 6, ['descricao', 'item']);
        $this->priorizar($query, $usados);

        return $query->orderBy('codigo')->limit(20)->get()->map(fn (NfseServico $s) => [
            'codigo' => $s->codigo,
            'descricao' => $s->descricao,
            'rotulo' => NfseCodigos::formatarServico($s->codigo).' - '.$s->descricao,
            'usado' => in_array($s->codigo, $usados, true),
        ])->values();
    }

    public function nbs(Request $request)
    {
        $correlatos = $this->correlatos($request)->pluck('nbs')->unique()->values()->all();
        $query = NfseNbs::query()->where('ativo', true);
        $q = (string) $request->query('q', '');

        // Sem texto digitado e com serviço escolhido: mostra só os NBS da correlação oficial.
        if (trim($q) === '' && $correlatos !== []) {
            $query->whereIn('codigo', $correlatos);
        } else {
            $this->filtrar($query, $q, 9, ['descricao']);
        }
        $this->priorizar($query, $correlatos);

        return $query->orderBy('codigo')->limit(20)->get()->map(fn (NfseNbs $n) => [
            'codigo' => $n->codigo,
            'descricao' => $n->descricao,
            'rotulo' => NfseCodigos::formatarNbs($n->codigo).' - '.$n->descricao,
            'sugerido' => in_array($n->codigo, $correlatos, true),
        ])->values();
    }

    public function classificacoes(Request $request)
    {
        $correlatos = $this->correlatos($request)->pluck('class_trib')->filter()->unique()->values()->all();
        $query = NfseClassificacao::query()->where('ativo', true);
        $this->filtrar($query, (string) $request->query('q', ''), 6, ['descricao', 'cst_descricao']);
        $this->priorizar($query, $correlatos);

        return $query->orderBy('codigo')->limit(30)->get()->map(fn (NfseClassificacao $c) => [
            'codigo' => $c->codigo,
            'descricao' => $c->descricao,
            'rotulo' => $c->codigo.' - '.$c->descricao.' (CST '.$c->cst.')',
            'sugerido' => in_array($c->codigo, $correlatos, true),
        ])->values();
    }

    public function indicadores(Request $request)
    {
        $correlatos = $this->correlatos($request)->pluck('ind_op')->filter()->unique()->values()->all();
        $query = NfseIndicadorOperacao::query()->where('ativo', true);
        $this->filtrar($query, (string) $request->query('q', ''), 6, ['descricao', 'local']);
        $this->priorizar($query, $correlatos);

        return $query->orderBy('codigo')->limit(30)->get()->map(fn (NfseIndicadorOperacao $i) => [
            'codigo' => $i->codigo,
            'descricao' => $i->descricao,
            'rotulo' => $i->codigo.' - '.$i->descricao.($i->local ? ' ('.$i->local.')' : ''),
            'sugerido' => in_array($i->codigo, $correlatos, true),
        ])->values();
    }

    public function municipios(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = Municipio::query();

        if (preg_match('/(?:^|\s-\s*)(\d{7})$/', $q, $m)) {
            // Rótulo já escolhido ("São Paulo/SP - 3550308"): o campo reenvia o texto ao receber o foco.
            $query->where('codigo', $m[1]);
        } elseif (preg_match('/^\d{2,7}/', $q, $m)) {
            $query->where('codigo', 'like', $m[0].'%');
        } elseif ($q !== '') {
            [$nome, $uf] = array_pad(array_map('trim', explode('/', $q, 2)), 2, '');
            $query->where('nome', 'like', '%'.addcslashes($nome, '%_\\').'%');
            if (preg_match('/^[A-Za-z]{2}\b/', $uf, $sigla)) {
                $query->where('uf', strtoupper($sigla[0]));
            }
        }

        return $query->orderBy('nome')->limit(20)->get()->map(fn (Municipio $m) => [
            'codigo' => $m->codigo,
            'descricao' => $m->nome.'/'.$m->uf,
            'rotulo' => $m->nome.'/'.$m->uf.' - '.$m->codigo,
        ])->values();
    }

    /**
     * @return Collection<int, NfseCorrelacao>
     */
    private function correlatos(Request $request)
    {
        $servico = preg_replace('/\D/', '', (string) $request->query('servico', '')) ?? '';
        if (strlen($servico) !== 6) {
            return collect();
        }

        $query = NfseCorrelacao::query()->where('item', NfseCodigos::item($servico));
        $nbs = preg_replace('/\D/', '', (string) $request->query('nbs', '')) ?? '';
        if (strlen($nbs) === 9) {
            $comNbs = (clone $query)->where('nbs', $nbs)->get();
            if ($comNbs->isNotEmpty()) {
                return $comNbs;
            }
        }

        return $query->get();
    }

    /**
     * @return list<string>
     */
    private function usadosNaEmpresa(string $coluna): array
    {
        return Produto::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->whereNotNull($coluna)
            ->distinct()
            ->limit(50)
            ->pluck($coluna)
            ->map(fn ($valor) => (string) $valor)
            ->all();
    }

    /**
     * @param  list<string>  $primeiros
     */
    private function priorizar(Builder $query, array $primeiros): void
    {
        if ($primeiros === []) {
            return;
        }

        $marcadores = implode(',', array_fill(0, count($primeiros), '?'));
        $query->orderByRaw("case when codigo in ({$marcadores}) then 0 else 1 end", $primeiros);
    }

    /**
     * Aceita o código com ou sem pontos ("14.01", "140101", "1.2001") ou parte da descrição.
     *
     * @param  list<string>  $colunas
     */
    private function filtrar(Builder $query, string $q, int $tamanho, array $colunas): void
    {
        $q = trim($q);
        if ($q === '') {
            return;
        }

        if (preg_match('/^([\d.]+)\s*(-|$)/u', $q, $m)) {
            $codigo = substr(preg_replace('/\D/', '', $m[1]) ?? '', 0, $tamanho);
            if ($codigo !== '') {
                $query->where('codigo', 'like', $codigo.'%');

                return;
            }
        }

        $escapado = addcslashes($q, '%_\\');
        $like = '%'.$escapado.'%';
        $query->where(function (Builder $filtro) use ($like, $colunas) {
            foreach ($colunas as $coluna) {
                $filtro->orWhere($coluna, 'like', $like);
            }
        });

        // "assist" traz "Assistência técnica" antes de "Unidade de atendimento, assistência...".
        $query->orderByRaw('case when descricao like ? then 0 else 1 end', [$escapado.'%']);
    }
}
