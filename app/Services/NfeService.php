<?php

namespace App\Services;

use App\Http\Requests\Nfes\NfeCreateRequest;
use App\Models\Empresas\Empresa;
use App\Models\Nfe;
use App\Models\Vendas\Venda;
use App\Services\Empresas\EmpresaService;
use App\Services\Vendas\VendaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use NFePHP\DA\NFe\Danfe;
use RuntimeException;
use Throwable;

class NfeService
{
    public function __construct(
        protected EmpresaService $empresaService,
        protected VendaService $vendaService
    ) {}

    public function index(Request $request)
    {
        $empresaId = (int) auth()->user()->empresa_id;

        $nfes = Nfe::query()
            ->where('empresa_id', $empresaId)
            ->with('venda')
            ->orderByDesc('id')
            ->paginate(30);

        return view('nfe.index', [
            'nfes' => $nfes,
        ]);
    }

    public function create()
    {
        $empresa = Empresa::query()->findOrFail((int) auth()->user()->empresa_id);
        $alertas = $this->alertasConfiguracao($empresa);

        return view('nfe.create', [
            'empresa' => $empresa,
            'alertas' => $alertas,
            'podeEmitir' => $alertas === [],
        ]);
    }

    public function store(NfeCreateRequest $request)
    {
        $empresa = Empresa::query()->findOrFail((int) auth()->user()->empresa_id);
        $alertas = $this->alertasConfiguracao($empresa);

        if ($alertas !== []) {
            return back()
                ->withInput()
                ->with('error', $alertas[0]);
        }

        try {
            $venda = $this->vendaService->criarVendaConfirmada($request);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (Throwable $e) {
            Log::error('Falha ao criar venda para NF-e', [
                'exception' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Não foi possível gerar a venda da NF-e. Verifique os itens e o estoque.');
        }

        return $this->emitir($venda);
    }

    /**
     * @return list<string>
     */
    private function alertasConfiguracao(Empresa $empresa): array
    {
        $alertas = [];

        $arquivo = $empresa->certificado_path
            ? storage_path('app/'.$empresa->certificado_path)
            : '';

        if (! $empresa->certificado_path || ! is_file($arquivo) || blank($empresa->certificado_senha_decrypted)) {
            $alertas[] = 'Configure o certificado digital A1 na empresa antes de emitir a NF-e.';
        }

        if (strlen(preg_replace('/\D/', '', (string) $empresa->cod_ibge) ?? '') !== 7) {
            $alertas[] = 'Informe o código IBGE do município (7 dígitos) na empresa.';
        }

        if (blank($empresa->ie)) {
            $alertas[] = 'Informe a inscrição estadual da empresa.';
        }

        if (blank($empresa->uf) || blank($empresa->cidade) || blank($empresa->logradouro) || blank($empresa->bairro)) {
            $alertas[] = 'Complete o endereço da empresa (logradouro, bairro, cidade e UF).';
        }

        if ((int) $empresa->nfe_destino !== 1) {
            $alertas[] = 'A emissão desta versão cobre apenas operação interna. Ajuste o destino na empresa.';
        }

        if ((int) $empresa->crt === 3) {
            $alertas[] = 'Regime Normal ainda não é emitido nesta versão. Use Simples Nacional.';
        }

        return $alertas;
    }

    public function show(Nfe $nfe)
    {
        $this->autorizarNfe($nfe);
        $nfe->load('venda');

        return view('nfe.show', [
            'nfe' => $nfe,
        ]);
    }

    public function emitir(Venda $venda)
    {
        $this->autorizarVenda($venda);

        if ($venda->situacao !== 'confirmada') {
            return back()->with('error', 'Apenas vendas confirmadas podem ter NF-e emitida.');
        }

        if (Nfe::query()
            ->where('empresa_id', $venda->empresa_id)
            ->where('venda_id', $venda->id)
            ->where('situacao', 'autorizada')
            ->exists()) {
            return back()->with('error', 'Esta venda já possui NF-e autorizada.');
        }

        if (Nfe::query()
            ->where('empresa_id', $venda->empresa_id)
            ->where('venda_id', $venda->id)
            ->whereIn('situacao', ['pendente', 'cancelada'])
            ->exists()) {
            return back()->with('error', 'Esta venda já possui uma NF-e em andamento ou cancelada.');
        }

        $empresa = Empresa::query()->findOrFail($venda->empresa_id);

        $nfe = DB::transaction(function () use ($venda, $empresa) {
            $numero = $this->empresaService->proximoNumeronNfe($empresa);

            $venda->update(['nfe_numero' => $numero]);
            $venda->refresh();

            return Nfe::query()->create([
                'empresa_id' => $empresa->id,
                'venda_id' => $venda->id,
                'numero' => $numero,
                'serie' => $empresa->nfe_serie ?: '001',
                'situacao' => 'pendente',
                'ambiente' => (int) ($empresa->nfe_ambiente ?: 2),
            ]);
        });

        try {
            $signedXml = (new NfeBuilderService($empresa))
                ->buildAndSign($venda->load(['itens.produto.unidadeMedida', 'cliente']));

            $nfe->update([
                'xml_assinado' => $signedXml,
                'chave_acesso' => $this->extrairChave($signedXml),
            ]);
        } catch (RuntimeException $e) {
            $nfe->update([
                'situacao' => 'rejeitada',
                'motivo_status' => mb_substr($e->getMessage(), 0, 255),
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', $e->getMessage());
        } catch (Throwable $e) {
            Log::error('Falha ao montar NF-e', [
                'venda_id' => $venda->id,
                'exception' => $e->getMessage(),
            ]);

            $nfe->update([
                'situacao' => 'rejeitada',
                'motivo_status' => 'Não foi possível montar a NF-e.',
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'Não foi possível montar a NF-e. Verifique os dados fiscais da empresa e dos produtos.');
        }

        $resultado = (new NfeSenderService($empresa))->enviar($signedXml, $empresa);

        return $this->aplicarResultadoEnvio($nfe, $resultado);
    }

    public function cancelar(Nfe $nfe, string $justificativa)
    {
        $this->autorizarNfe($nfe);

        if (! $nfe->podeCancelar()) {
            return back()->with('error', 'Só é possível cancelar NF-e autorizada nas últimas 24 horas.');
        }

        if (blank($nfe->chave_acesso) || blank($nfe->protocolo)) {
            return back()->with('error', 'NF-e sem chave ou protocolo para cancelamento.');
        }

        $empresa = Empresa::query()->findOrFail($nfe->empresa_id);
        $sender = new NfeSenderService($empresa);
        $resultado = $sender->cancelar($nfe->chave_acesso, $justificativa, $nfe->protocolo);

        if (($resultado['status'] ?? '') === 'cancelada') {
            DB::transaction(function () use ($nfe, $resultado) {
                $nfe->update([
                    'situacao' => 'cancelada',
                    'codigo_status' => $resultado['codigo'] ?? '101',
                    'motivo_status' => mb_substr((string) ($resultado['motivo'] ?? 'Cancelamento homologado'), 0, 255),
                ]);
            });

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('success', 'NF-e cancelada com sucesso.');
        }

        return back()->with(
            'error',
            'Falha no cancelamento: '.($resultado['motivo'] ?? 'erro desconhecido')
            .(isset($resultado['codigo']) ? ' Código: '.$resultado['codigo'] : '')
        );
    }

    public function danfe(Nfe $nfe)
    {
        $this->autorizarNfe($nfe);

        if (blank($nfe->xml_autorizado)) {
            return back()->with('error', 'NF-e ainda não autorizada.');
        }

        try {
            $danfe = new Danfe($nfe->xml_autorizado);
            $pdf = $danfe->render();
        } catch (Throwable $e) {
            Log::error('Falha ao gerar DANFE', [
                'nfe_id' => $nfe->id,
                'exception' => $e->getMessage(),
            ]);

            return back()->with('error', 'Não foi possível gerar o DANFE desta NF-e.');
        }

        $nome = 'danfe_'.$nfe->numero.'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nome.'"',
        ]);
    }

    private function aplicarResultadoEnvio(Nfe $nfe, array $resultado)
    {
        $status = $resultado['status'] ?? 'erro';

        if ($status === 'autorizada') {
            DB::transaction(function () use ($nfe, $resultado) {
                $nfe->update([
                    'situacao' => 'autorizada',
                    'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                    'protocolo' => $resultado['protocolo'] ?? null,
                    'xml_autorizado' => $resultado['xml_autorizado'] ?? null,
                    'codigo_status' => $resultado['codigo'] ?? '100',
                    'motivo_status' => mb_substr((string) ($resultado['motivo'] ?? 'Autorizado o uso da NF-e'), 0, 255),
                    'data_autorizacao' => now(),
                ]);
            });

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('success', 'NF-e autorizada com sucesso.');
        }

        DB::transaction(function () use ($nfe, $resultado, $status) {
            $nfe->update([
                'situacao' => 'rejeitada',
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'codigo_status' => $resultado['codigo'] ?? null,
                'motivo_status' => mb_substr((string) ($resultado['motivo'] ?? 'Falha no envio'), 0, 255),
            ]);
        });

        $motivo = $resultado['motivo'] ?? 'erro desconhecido';
        $codigo = $resultado['codigo'] ?? null;

        return redirect()
            ->route('nfe.show', $nfe)
            ->with('error', 'NF-e rejeitada: '.$motivo.($codigo ? ' Código: '.$codigo : ''));
    }

    private function extrairChave(string $xml): ?string
    {
        try {
            $chave = \NFePHP\Common\Keys::extractAccessKey($xml);

            return $chave !== '' ? $chave : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function autorizarNfe(Nfe $nfe): void
    {
        if ((int) $nfe->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }

    private function autorizarVenda(Venda $venda): void
    {
        if ((int) $venda->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
