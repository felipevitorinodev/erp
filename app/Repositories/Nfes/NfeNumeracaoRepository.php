<?php

namespace App\Repositories\Nfes;

use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Nfes\NfeInutilizacao;
use App\Models\Nfes\NfeNumeracao;
use Illuminate\Support\Facades\DB;

class NfeNumeracaoRepository
{
    public function proximo(Empresa $empresa, int $modelo = Nfe::MODELO_NFE): string
    {
        return DB::transaction(function () use ($empresa, $modelo) {
            $registro = Empresa::query()
                ->where('id', $empresa->id)
                ->lockForUpdate()
                ->firstOrFail(['id', 'nfe_ambiente', 'nfe_serie', 'nfce_serie', 'nfe_sequencia']);

            $ambiente = (int) ($registro->nfe_ambiente ?: 2);
            $serieBruta = $modelo === Nfe::MODELO_NFCE
                ? ($registro->nfce_serie ?: '001')
                : ($registro->nfe_serie ?: '001');
            $serie = str_pad(preg_replace('/\D/', '', (string) $serieBruta) ?: '1', 3, '0', STR_PAD_LEFT);
            $maiorUsado = $this->maiorNumeroUsado($registro->id, $ambiente, $serie, $modelo);

            $numeracao = NfeNumeracao::query()
                ->where('empresa_id', $registro->id)
                ->where('modelo', $modelo)
                ->where('ambiente', $ambiente)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->first();

            if (! $numeracao) {
                $jaTemOutroAmbiente = NfeNumeracao::query()
                    ->where('empresa_id', $registro->id)
                    ->where('modelo', $modelo)
                    ->exists();
                $ultimo = $maiorUsado;
                if (! $jaTemOutroAmbiente && $modelo === Nfe::MODELO_NFE) {
                    $ultimo = max($ultimo, max(0, (int) $registro->nfe_sequencia - 1));
                }

                $numeracao = NfeNumeracao::query()->create([
                    'empresa_id' => $registro->id,
                    'modelo' => $modelo,
                    'ambiente' => $ambiente,
                    'serie' => $serie,
                    'ultimo_numero' => $ultimo,
                ]);
                $numeracao = NfeNumeracao::query()->whereKey($numeracao->id)->lockForUpdate()->first();
            }

            $candidato = max($numeracao->ultimo_numero + 1, $maiorUsado + 1);
            $candidato = $this->pularFaixasInutilizadas($registro->id, $modelo, $ambiente, $serie, $candidato);

            $numeracao->ultimo_numero = $candidato;
            $numeracao->save();

            if ($modelo === Nfe::MODELO_NFE) {
                $registro->nfe_sequencia = $candidato + 1;
                $registro->save();
            }

            return str_pad((string) $candidato, 9, '0', STR_PAD_LEFT);
        });
    }

    public function alinharSequenciaExibida(Empresa $empresa): void
    {
        $atual = $empresa->fresh();
        if ($atual instanceof Empresa) {
            $empresa = $atual;
        }

        $ambiente = (int) ($empresa->nfe_ambiente ?: 2);
        $serie = $empresa->nfe_serie ?: '001';
        $maior = $this->maiorNumeroUsado($empresa->id, $ambiente, $serie, Nfe::MODELO_NFE);

        $numeracao = NfeNumeracao::query()->firstOrCreate(
            [
                'empresa_id' => $empresa->id,
                'modelo' => Nfe::MODELO_NFE,
                'ambiente' => $ambiente,
                'serie' => $serie,
            ],
            ['ultimo_numero' => $maior]
        );

        Empresa::query()->whereKey($empresa->id)->update([
            'nfe_sequencia' => $numeracao->ultimo_numero + 1,
        ]);
    }

    private function maiorNumeroUsado(int $empresaId, int $ambiente, string $serie, int $modelo): int
    {
        return (int) Nfe::withTrashed()
            ->where('empresa_id', $empresaId)
            ->where('modelo', $modelo)
            ->where('ambiente', $ambiente)
            ->where('serie', $serie)
            ->max('numero');
    }

    private function pularFaixasInutilizadas(int $empresaId, int $modelo, int $ambiente, string $serie, int $candidato): int
    {
        $faixas = NfeInutilizacao::query()
            ->where('empresa_id', $empresaId)
            ->where('modelo', $modelo)
            ->where('ambiente', $ambiente)
            ->where('serie', $serie)
            ->where('situacao', 'autorizada')
            ->where('numero_final', '>=', $candidato)
            ->orderBy('numero_inicial')
            ->get(['numero_inicial', 'numero_final']);

        foreach ($faixas as $faixa) {
            if ($candidato >= $faixa->numero_inicial && $candidato <= $faixa->numero_final) {
                $candidato = $faixa->numero_final + 1;
            }
        }

        return $candidato;
    }
}
