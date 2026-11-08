<?php

namespace App\Services\Nfes;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use NFePHP\Common\TimeZoneByUF;
use RuntimeException;
use Throwable;

/**
 * dhEmi segue a data escolhida na nota. No dia corrente usa o horário de agora,
 * para a SEFAZ não receber uma emissão à meia-noite. Um dia à frente é recusado:
 * a SEFAZ rejeita dhEmi posterior ao recebimento. dhSaiEnt só existe na NF-e
 * e nunca fica anterior à emissão.
 */
class NfeDatas
{
    public function fusoDaUf(?string $uf): string
    {
        try {
            return TimeZoneByUF::get(strtoupper(trim((string) $uf)));
        } catch (Throwable) {
            return 'America/Sao_Paulo';
        }
    }

    public function diaPosterior(?CarbonInterface $dataVenda, string $fuso): bool
    {
        if ($dataVenda === null) {
            return false;
        }

        $hoje = Carbon::now($fuso)->startOfDay();
        $dia = Carbon::parse($dataVenda->format('Y-m-d'), $fuso)->startOfDay();

        return $dia->gt($hoje);
    }

    public function emissao(?CarbonInterface $dataVenda, string $fuso): Carbon
    {
        $agora = Carbon::now($fuso);

        if ($this->diaPosterior($dataVenda, $fuso)) {
            throw new RuntimeException('A data de emissão não pode ser posterior a hoje.');
        }

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
