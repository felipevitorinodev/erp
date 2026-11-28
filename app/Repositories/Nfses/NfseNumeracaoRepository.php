<?php

namespace App\Repositories\Nfses;

use App\Models\Empresas\Empresa;
use App\Models\Nfses\Nfse;
use App\Models\Nfses\NfseNumeracao;
use Illuminate\Support\Facades\DB;

/**
 * Número da DPS por empresa, ambiente e série. Reserva com trava de linha para duas emissões
 * simultâneas não pegarem o mesmo número; nunca volta atrás do maior número já gravado.
 */
class NfseNumeracaoRepository
{
    public function proximo(Empresa $empresa, int $ambiente, string $serie): int
    {
        return DB::transaction(function () use ($empresa, $ambiente, $serie) {
            Empresa::query()->whereKey($empresa->id)->lockForUpdate()->first(['id']);

            $maiorUsado = (int) Nfse::withTrashed()
                ->where('empresa_id', $empresa->id)
                ->where('ambiente', $ambiente)
                ->where('serie', $serie)
                ->max('numero');

            $numeracao = NfseNumeracao::query()
                ->where('empresa_id', $empresa->id)
                ->where('ambiente', $ambiente)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->first();

            if (! $numeracao) {
                $numeracao = NfseNumeracao::query()->create([
                    'empresa_id' => $empresa->id,
                    'ambiente' => $ambiente,
                    'serie' => $serie,
                    'ultimo_numero' => $maiorUsado,
                ]);
            }

            $numero = max((int) $numeracao->ultimo_numero, $maiorUsado) + 1;
            $numeracao->ultimo_numero = $numero;
            $numeracao->save();

            return $numero;
        });
    }
}
