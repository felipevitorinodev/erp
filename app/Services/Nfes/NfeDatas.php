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
    /**
     * NF-e (modelo 55) com dhEmi há mais de 30 dias volta com rejeição 228 (MOC, regra B09-20).
     * Entre 7 e 30 dias a SEFAZ autoriza com cStat 150 ("autorização fora de prazo", NT 2025.001).
     * Como dia passado sai às 12:00, 30 dias de calendário já podem passar das 720 horas: bloqueia a partir de 30.
     */
    public const ATRASO_MAXIMO_DIAS = 30;

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

    /** Dias de calendário entre a data da nota e hoje, no fuso da UF. 0 para hoje, data futura ou vazia. */
    public function diasDeAtraso(?CarbonInterface $dataVenda, string $fuso): int
    {
        if ($dataVenda === null) {
            return 0;
        }

        $hoje = Carbon::now($fuso)->startOfDay();
        $dia = Carbon::parse($dataVenda->format('Y-m-d'), $fuso)->startOfDay();

        return $dia->lt($hoje) ? (int) $dia->diffInDays($hoje) : 0;
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
