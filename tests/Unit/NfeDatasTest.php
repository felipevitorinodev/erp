<?php

namespace Tests\Unit;

use App\Services\Nfes\NfeDatas;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class NfeDatasTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_emissao_de_hoje_usa_o_horario_atual(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 15:40:00', 'America/Sao_Paulo'));
        $emissao = (new NfeDatas())->emissao(Carbon::parse('2026-11-08'), 'America/Sao_Paulo');

        $this->assertSame('2026-11-08 15:40:00', $emissao->format('Y-m-d H:i:s'));
    }

    public function test_emissao_de_outro_dia_fica_ao_meio_dia(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 15:40:00', 'America/Sao_Paulo'));
        $emissao = (new NfeDatas())->emissao(Carbon::parse('2026-11-01'), 'America/Sao_Paulo');

        $this->assertSame('2026-11-01T12:00:00-03:00', $emissao->format('Y-m-d\TH:i:sP'));
    }

    public function test_saida_no_mesmo_dia_repete_a_emissao(): void
    {
        $dhEmi = Carbon::parse('2026-11-08 15:40:00', 'America/Sao_Paulo');
        $saida = (new NfeDatas())->saida(Carbon::parse('2026-11-08'), $dhEmi);

        $this->assertSame($dhEmi->format('Y-m-d\TH:i:sP'), $saida);
    }

    public function test_saida_posterior_mantem_o_horario_da_emissao(): void
    {
        $dhEmi = Carbon::parse('2026-11-08 15:40:10', 'America/Sao_Paulo');
        $saida = (new NfeDatas())->saida(Carbon::parse('2026-11-10'), $dhEmi);

        $this->assertSame('2026-11-10T15:40:10-03:00', $saida);
    }

    public function test_saida_vazia_nao_entra_no_xml(): void
    {
        $this->assertNull((new NfeDatas())->saida(null, Carbon::parse('2026-11-08 15:40:00', 'America/Sao_Paulo')));
    }

    public function test_saida_anterior_e_recusada(): void
    {
        $this->expectException(RuntimeException::class);

        (new NfeDatas())->saida(
            Carbon::parse('2026-11-07'),
            Carbon::parse('2026-11-08 15:40:00', 'America/Sao_Paulo')
        );
    }
}
