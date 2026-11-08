<?php

namespace Tests\Unit;

use App\Comandos\EntradaInvalida;
use App\Comandos\Leitor;
use App\Comandos\PeriodoFalado;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LeitorComandosTest extends TestCase
{
    private function hoje(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 11, 7);
    }

    #[DataProvider('valores')]
    public function test_le_valor_no_formato_brasileiro(string $frase, float $esperado): void
    {
        $this->assertSame($esperado, (new Leitor($frase))->valor());
    }

    public static function valores(): array
    {
        return [
            'com R$ e milhar' => ['aluguel R$ 1.234,56', 1234.56],
            'com vírgula' => ['150,5 do cliente', 150.5],
            'milhar sem centavos' => ['pagar 1.500 dia 10', 1500.0],
            'com a palavra valor' => ['valor 89,90 vence amanhã', 89.9],
            'com "reais"' => ['me deve 150 reais', 150.0],
        ];
    }

    #[DataProvider('datas')]
    public function test_le_datas_do_jeito_que_se_fala(string $frase, string $esperada): void
    {
        $this->assertSame($esperada, (new Leitor($frase))->data($this->hoje())?->toDateString());
    }

    public static function datas(): array
    {
        return [
            'amanhã com acento' => ['vence amanhã', '2026-11-08'],
            'dia e mês' => ['vence 15/12', '2026-12-15'],
            'mês que já passou vai para o ano seguinte' => ['vencimento 10/01', '2027-01-10'],
            'ano com dois dígitos' => ['para 05/03/27', '2027-03-05'],
            'em N dias' => ['em 30 dias', '2026-12-07'],
            'dia do mês que já passou' => ['vence dia 5', '2026-12-05'],
            'dia do mês que ainda vem' => ['vence dia 20', '2026-11-20'],
            'dia da semana com vence' => ['vence sexta', '2026-11-13'],
            'dia da semana com para' => ['para segunda-feira', '2026-11-09'],
            'próxima sexta' => ['na próxima sexta', '2026-11-13'],
            'fim do mês' => ['vence no fim do mês', '2026-11-30'],
            'semana que vem' => ['semana que vem', '2026-11-14'],
            'mês que vem' => ['mês que vem', '2026-12-07'],
        ];
    }

    public function test_dia_da_semana_solto_nao_vira_data(): void
    {
        $this->assertNull((new Leitor('referente a segunda parcela'))->data($this->hoje()));
    }

    public function test_data_errada_informa_o_trecho_para_corrigir(): void
    {
        try {
            (new Leitor('350 vence 31/02 referente a energia'))->data($this->hoje());
            $this->fail('Deveria recusar 31/02.');
        } catch (EntradaInvalida $e) {
            $this->assertSame('vence 31/02', $e->trecho);
        }
    }

    public function test_grupos_devolve_o_texto_como_foi_digitado(): void
    {
        $this->assertSame('Maria José', Leitor::grupos('/^a (?<nome>.+) comprou/u', 'a Maria José comprou 2 coca')['nome']);
        $this->assertNull(Leitor::grupos('/^vendi/u', 'a Maria comprou'));
    }

    public function test_data_que_nao_existe_avisa(): void
    {
        $this->expectException(EntradaInvalida::class);
        $this->expectExceptionMessage('A data 31/02 não existe no calendário.');

        (new Leitor('vence 31/02'))->data($this->hoje());
    }

    public function test_separa_documento_contatos_e_nome_mantendo_acentos(): void
    {
        $leitor = new Leitor('José Ávila cpf 52998224725 tel (11) 98765-4321 e-mail Jose@Exemplo.com.br');

        $contatos = $leitor->contatos();

        $this->assertSame(['tipo' => 'cpf', 'numero' => '529.982.247-25'], $contatos['documento']);
        $this->assertSame('(11) 98765-4321', $contatos['telefone']);
        $this->assertSame('jose@exemplo.com.br', $contatos['email']);
        $this->assertSame('José Ávila', $leitor->resto());
    }

    public function test_cnpj_com_mascara_sem_rotulo(): void
    {
        $contatos = (new Leitor('Atacado Sul 11.222.333/0001-81'))->contatos();

        $this->assertSame(['tipo' => 'cnpj', 'numero' => '11.222.333/0001-81'], $contatos['documento']);
    }

    public function test_documento_com_digitos_faltando_avisa(): void
    {
        $this->expectException(EntradaInvalida::class);
        $this->expectExceptionMessage('tem 10 dígitos');

        (new Leitor('Maria cpf 1234567890'))->contatos();
    }

    public function test_periodo_falado_entende_ontem_semana_e_intervalo(): void
    {
        $hoje = $this->hoje();

        $ontem = PeriodoFalado::interpretar('ontem', $hoje);
        $this->assertSame('2026-11-06', $ontem->inicio->toDateString());
        $this->assertSame('2026-11-06', $ontem->fim->toDateString());
        $this->assertSame('Ontem (06/11/2026)', $ontem->rotulo());

        $semana = PeriodoFalado::interpretar('esta semana', $hoje);
        $this->assertSame('2026-11-02', $semana->inicio->toDateString());
        $this->assertSame('2026-11-08', $semana->fim->toDateString());

        $passado = PeriodoFalado::interpretar('mês passado', $hoje);
        $this->assertSame('2026-10-01', $passado->inicio->toDateString());
        $this->assertSame('2026-10-31', $passado->fim->toDateString());

        // Sem ano, a busca fica no ano corrente — mesmo quando o dia já passou.
        $faixa = PeriodoFalado::interpretar('de 01/11 a 05/11', $hoje);
        $this->assertSame('2026-11-01', $faixa->inicio->toDateString());
        $this->assertSame('2026-11-05', $faixa->fim->toDateString());

        $this->assertSame('2026-11-13', PeriodoFalado::dataUnica('sexta', $hoje)?->toDateString());
        $this->assertSame('2026-11-30', PeriodoFalado::interpretar('fim do mês', $hoje)->inicio->toDateString());
        $this->assertNull(PeriodoFalado::interpretar('qualquer coisa', $hoje));
    }

    public function test_periodo_falado_recusa_data_que_nao_existe(): void
    {
        $this->expectException(EntradaInvalida::class);
        $this->expectExceptionMessage('A data 31/02 não existe no calendário.');

        PeriodoFalado::interpretar('31/02', $this->hoje());
    }

    public function test_nome_com_rotulo_para_na_proxima_palavra_chave(): void
    {
        $leitor = new Leitor('150 do cliente Maria da Silva vence amanhã referente a conserto');
        $leitor->data($this->hoje());
        $leitor->valor();

        $this->assertSame('Maria da Silva', $leitor->rotulado('cliente'));
        $this->assertSame('conserto', $leitor->descricao());
        $this->assertSame('', $leitor->resto());
    }
}
