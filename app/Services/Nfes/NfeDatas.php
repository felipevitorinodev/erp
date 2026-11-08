<?php

namespace App\Services\Nfes;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use RuntimeException;

/**
 * dhEmi segue a data escolhida na nota. No dia corrente usa o horário de agora,
 * para a SEFAZ não receber uma emissão à meia-noite. dhSaiEnt só existe na NF-e
 * e nunca fica anterior à emissão.
 */
class NfeDatas
{
    public function emissao(?CarbonInterface $dataVenda, string $fuso): Carbon
    {
        $agora = Carbon::now($fuso);
        $dia = $dataVenda
            ? Carbon::parse($dataVenda->format('Y-m-d'), $fuso)->startOfDay()
            : $agora->copy()->startOfDay();

        if ($dia->isSameDay($agora)) {
            return $agora;
        }

        return $dia->setTime(12, 0, 0);
    }

    public function saida(?CarbonInterface $dataSaida, Carbon $dhEmi): ?string
    {
        if ($dataSaida === null) {
            return null;
        }

        $saida = Carbon::parse($dataSaida->format('Y-m-d'), $dhEmi->timezone)->startOfDay();
        $emissao = $dhEmi->copy()->startOfDay();

        if ($saida->lt($emissao)) {
            throw new RuntimeException('A data de saída não pode ser anterior à data de emissão.');
        }

        $momento = $saida->equalTo($emissao)
            ? $dhEmi->copy()
            : $saida->setTime($dhEmi->hour, $dhEmi->minute, $dhEmi->second);

        return $momento->format('Y-m-d\TH:i:sP');
    }
}
