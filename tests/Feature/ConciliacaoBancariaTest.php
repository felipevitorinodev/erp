<?php

namespace Tests\Feature;

use App\Models\ConciliacaoBancaria\ExtratoBancario;
use App\Models\ConciliacaoBancaria\TransacaoBancaria;
use App\Models\ContasPagar\ContaPagar;
use App\Models\ContasReceber\ContaReceber;
use App\Models\Empresas\Empresa;
use App\Models\Usuarios\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class ConciliacaoBancariaTest extends TestCase
{
    use RefreshDatabase;

    private Empresa $empresa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->empresa = Empresa::create(['razao_social' => 'Empresa A', 'cnpj' => '11222333000181']);
        $this->actingAs(User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'perfil' => 'admin',
            'ativo' => true,
        ]));
    }

    public function test_importa_ofx_e_nao_duplica_arquivo(): void
    {
        $arquivo = UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofx());

        $resposta = $this->post(route('conciliacao-bancaria.importar'), ['arquivo' => $arquivo]);

        $extrato = ExtratoBancario::firstOrFail();
        $resposta->assertRedirect(route('conciliacao-bancaria.show', $extrato));
        $this->assertSame('123456', $extrato->conta_bancaria);
        $this->assertDatabaseCount('transacoes_bancarias', 3);
        $this->get(route('conciliacao-bancaria.index'))->assertOk();
        $this->get(route('conciliacao-bancaria.show', $extrato))->assertOk();

        $this->post(route('conciliacao-bancaria.importar'), [
            'arquivo' => UploadedFile::fake()->createWithContent('extrato-repetido.ofx', $this->ofx()),
        ])->assertSessionHasErrors('arquivo');

        $this->assertDatabaseCount('extratos_bancarios', 1);
        $this->assertDatabaseCount('transacoes_bancarias', 3);
    }

    public function test_importacao_usa_o_mesmo_envio_de_arquivo_da_entrada_de_xml(): void
    {
        $modal = '/<div class="modal-overlay" id="modal-importar-ofx" data-modal\s*(hidden)?\s*>/';

        $pagina = $this->get(route('conciliacao-bancaria.index'))
            ->assertOk()
            ->assertSee('data-abrir-modal="modal-importar-ofx"', false)
            ->assertSee('data-upload-area', false)
            ->assertSee('name="arquivo" id="arquivo" class="upload-area__input" accept=".ofx"', false)
            ->assertSee('ilustracao--extrato', false);
        preg_match($modal, $pagina->getContent(), $achado);
        $this->assertSame('hidden', $achado[1] ?? null, 'O modal deve abrir só pelo botão.');

        // Arquivo recusado pelo servidor: o modal volta aberto, com o motivo embaixo da área.
        // (A sessão dos testes não carrega o pacote de erros entre requisições; a tela é montada direto.)
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'arquivo' => 'Este mesmo arquivo OFX já foi importado.',
        ])));
        $html = view('conciliacao-bancaria.index', ['extratos' => ExtratoBancario::query()->paginate(20)])->render();
        $this->assertStringContainsString('upload-area--erro', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('<span class="form-error" id="arquivo-erro">Este mesmo arquivo OFX já foi importado.</span>', $html);
        preg_match($modal, $html, $achado);
        $this->assertNotEmpty($achado);
        $this->assertArrayNotHasKey(1, $achado, 'Com erro do arquivo o modal já vem aberto.');

        view()->share('errors', new ViewErrorBag);
        $this->get(route('entrada-estoque.index'))
            ->assertOk()
            ->assertSee('data-abrir-modal="modal-importar-xml"', false)
            ->assertSee('name="xml_file" id="input-xml-file" class="upload-area__input" accept=".xml"', false);
    }

    public function test_le_ofx_de_banco_com_virgula_decimal_latin1_e_memo_no_fim_do_bloco(): void
    {
        $this->assertSame('Recebimento', $this->importarOfxPadrao()->where('valor', 80)->value('memo'));

        $ofx = mb_convert_encoding(<<<'OFX'
OFXHEADER:100
DATA:OFXSGML
CHARSET:1252

<OFX>
<BANKID>237
<ACCTID>98765
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20261118[-3:BRT]
<TRNAMT>-12,34
<FITID>X-1
<NAME>Tarifa bancária
<MEMO>Manutenção de conta
</STMTTRN>
</OFX>
OFX, 'Windows-1252', 'UTF-8');

        $this->post(route('conciliacao-bancaria.importar'), [
            'arquivo' => UploadedFile::fake()->createWithContent('bradesco.ofx', $ofx),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $transacao = TransacaoBancaria::where('fitid', 'X-1')->firstOrFail();
        $this->assertSame(-12.34, (float) $transacao->valor);
        $this->assertSame('Tarifa bancária', $transacao->descricao);
        $this->assertSame('Manutenção de conta', $transacao->memo);
        $this->assertSame('2026-11-18', $transacao->data_transacao->toDateString());
    }

    public function test_extrato_so_com_transacoes_ja_importadas_e_recusado(): void
    {
        $this->importarOfxPadrao();

        // Mesmo conteúdo com outra linha em branco: outro arquivo, mesmas transações.
        $this->post(route('conciliacao-bancaria.importar'), [
            'arquivo' => UploadedFile::fake()->createWithContent('extrato-copia.ofx', $this->ofx()."\n"),
        ])->assertSessionHasErrors(['arquivo' => 'Todas as transações deste arquivo já foram importadas em outro extrato.']);

        $this->assertDatabaseCount('extratos_bancarios', 1);
    }

    public function test_revisao_do_extrato_mostra_situacao_da_transacao_e_filtra(): void
    {
        $this->importarOfxPadrao();
        $extrato = ExtratoBancario::firstOrFail();
        TransacaoBancaria::where('valor', -2.5)->update(['situacao' => 'ignorada', 'motivo_ignorado' => 'Tarifa']);

        $this->get(route('conciliacao-bancaria.show', $extrato))
            ->assertOk()
            ->assertSee('<span class="badge badge--warning">Pendente</span>', false)
            ->assertSee('<span class="badge badge--neutral">Ignorada</span>', false)
            ->assertDontSee('>Aberta<', false);

        $this->get(route('conciliacao-bancaria.show', [$extrato, 'situacao' => 'ignorada']))
            ->assertOk()
            ->assertSee('Tarifa bancária')
            ->assertDontSee('Pagamento cliente');
    }

    public function test_concilia_credito_e_debito_com_baixas_e_pode_ignorar_sem_lancar_despesa(): void
    {
        $this->post(route('conciliacao-bancaria.importar'), [
            'arquivo' => UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofx()),
        ]);
        $credito = TransacaoBancaria::where('valor', 80)->firstOrFail();
        $debito = TransacaoBancaria::where('valor', -35)->firstOrFail();

        $receber = ContaReceber::create([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Venda cliente',
            'valor' => 100,
            'valor_pago' => 0,
            'data_vencimento' => now()->toDateString(),
            'situacao' => 'aberta',
        ]);
        $pagar = ContaPagar::create([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Fornecedor',
            'valor' => 50,
            'valor_pago' => 0,
            'data_vencimento' => now()->toDateString(),
            'situacao' => 'aberta',
        ]);

        $this->post(route('conciliacao-bancaria.conciliar', $credito), ['conta_id' => $receber->id])
            ->assertSessionHas('success');
        $this->post(route('conciliacao-bancaria.conciliar', $debito), ['conta_id' => $pagar->id])
            ->assertSessionHas('success');

        $this->assertSame('parcial', $receber->fresh()->situacao);
        $this->assertSame(80.0, (float) $receber->fresh()->valor_pago);
        $this->assertSame('parcial', $pagar->fresh()->situacao);
        $this->assertSame(35.0, (float) $pagar->fresh()->valor_pago);
        $this->assertSame('conciliada', $credito->fresh()->situacao);
        $this->assertSame($receber->id, $credito->fresh()->conta_receber_id);
        $this->assertSame($pagar->id, $debito->fresh()->conta_pagar_id);

        $pendente = TransacaoBancaria::where('situacao', 'pendente')->firstOrFail();
        $this->post(route('conciliacao-bancaria.ignorar', $pendente), ['motivo' => 'Tarifa bancária'])
            ->assertSessionHas('success');
        $this->assertSame('ignorada', $pendente->fresh()->situacao);
        $this->assertSame(1, ContaPagar::count());
    }

    public function test_recusa_conciliacao_acima_do_saldo_e_isola_contas_de_outra_empresa(): void
    {
        $extrato = ExtratoBancario::create([
            'empresa_id' => $this->empresa->id,
            'arquivo_nome' => 'extrato.ofx',
            'arquivo_hash' => hash('sha256', 'arquivo'),
            'conta_bancaria' => '123456',
        ]);
        $transacao = $extrato->transacoes()->create([
            'empresa_id' => $this->empresa->id,
            'fingerprint' => hash('sha256', 'tx'),
            'data_transacao' => now()->toDateString(),
            'valor' => 120,
            'descricao' => 'Crédito',
            'situacao' => 'pendente',
        ]);
        $outraEmpresa = Empresa::create(['razao_social' => 'Empresa B', 'cnpj' => '22333444000190']);
        $outraConta = ContaReceber::create([
            'empresa_id' => $outraEmpresa->id,
            'descricao' => 'Conta externa',
            'valor' => 200,
            'valor_pago' => 0,
            'data_vencimento' => now()->toDateString(),
            'situacao' => 'aberta',
        ]);
        $conta = ContaReceber::create([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Saldo menor',
            'valor' => 100,
            'valor_pago' => 0,
            'data_vencimento' => now()->toDateString(),
            'situacao' => 'aberta',
        ]);

        $this->get(route('conciliacao-bancaria.contas', ['tipo' => 'receber', 'q' => 'Conta externa']))
            ->assertDontSee('Conta externa');
        $this->post(route('conciliacao-bancaria.conciliar', $transacao), ['conta_id' => $outraConta->id])
            ->assertSessionHas('error');
        $this->post(route('conciliacao-bancaria.conciliar', $transacao), ['conta_id' => $conta->id])
            ->assertSessionHas('error');

        $this->assertSame('pendente', $transacao->fresh()->situacao);
        $this->assertSame(0.0, (float) $conta->fresh()->valor_pago);
    }

    public function test_registra_historico_e_lembrete_de_cobranca(): void
    {
        $conta = ContaReceber::create([
            'empresa_id' => $this->empresa->id,
            'descricao' => 'Conta vencida',
            'valor' => 100,
            'valor_pago' => 0,
            'data_vencimento' => now()->subDays(5)->toDateString(),
            'situacao' => 'aberta',
        ]);

        $this->post(route('cobranca.registrar', $conta), [
            'canal' => 'whatsapp',
            'resultado' => 'Cliente pediu retorno na próxima semana.',
            'contatado_em' => today()->toDateString(),
            'proxima_cobranca_em' => today()->addDays(7)->toDateString(),
        ])->assertRedirect(route('conta-receber.show', $conta));

        $this->assertSame('whatsapp', $conta->cobrancas()->firstOrFail()->canal);
        $this->assertSame(today()->addDays(7)->toDateString(), $conta->cobrancas()->firstOrFail()->proxima_cobranca_em->toDateString());
        $this->assertSame(today()->addDays(7)->toDateString(), $conta->fresh()->proxima_cobranca_em->toDateString());
        $this->get(route('cobranca.index'))->assertDontSee('Conta vencida');
        $this->get(route('conta-receber.show', $conta))->assertOk();

        $conta->update(['proxima_cobranca_em' => today()->toDateString()]);
        $this->get(route('cobranca.index'))->assertSee('Conta vencida');
    }

    /** @return Builder<TransacaoBancaria> */
    private function importarOfxPadrao()
    {
        $this->post(route('conciliacao-bancaria.importar'), [
            'arquivo' => UploadedFile::fake()->createWithContent('extrato.ofx', $this->ofx()),
        ])->assertSessionHasNoErrors();

        return TransacaoBancaria::query();
    }

    private function ofx(): string
    {
        return <<<'OFX'
OFXHEADER:100
DATA:OFXSGML

<OFX>
<BANKID>001
<BANKACCTFROM>
<ACCTID>123456
<BANKTRANLIST>
<DTSTART>20261101000000
<DTEND>20261130235959
<STMTTRN>
<TRNTYPE>CREDIT
<DTPOSTED>20261115090000
<TRNAMT>80.00
<FITID>CR-001
<NAME>Pagamento cliente
<MEMO>Recebimento
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20261116100000
<TRNAMT>-35.00
<FITID>DB-001
<NAME>Pagamento fornecedor
<MEMO>Fornecedor
</STMTTRN>
<STMTTRN>
<TRNTYPE>DEBIT
<DTPOSTED>20261117100000
<TRNAMT>-2.50
<FITID>DB-002
<NAME>Tarifa bancária
<MEMO>Tarifa
</STMTTRN>
</BANKTRANLIST>
</OFX>
OFX;
    }
}
