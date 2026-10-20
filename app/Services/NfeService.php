<?php

namespace App\Services;

use App\Http\Requests\Nfes\NfeCreateRequest;
use App\Models\Empresas\Empresa;
use App\Models\Nfe;
use App\Models\Vendas\Venda;
use App\Services\Empresas\EmpresaService;
use App\Services\Vendas\VendaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use NFePHP\Common\Certificate;
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
        $avisoCertificado = null;

        if ($alertas === [] && $empresa->certificado_path) {
            $validade = $this->validadeCertificado($empresa);
            if ($validade !== null && $validade->lt(now()->addDays(30))) {
                $avisoCertificado = 'O certificado digital vence em '.$validade->format('d/m/Y').'. Providencie a renovação.';
            }
        }

        return view('nfe.create', [
            'empresa' => $empresa,
            'alertas' => $alertas,
            'avisoCertificado' => $avisoCertificado,
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
        } else {
            $validade = $this->validadeCertificado($empresa);

            if ($validade === null) {
                $alertas[] = 'O certificado digital da empresa é inválido ou a senha está incorreta.';
            } elseif ($validade->isPast()) {
                $alertas[] = 'O certificado digital venceu em '.$validade->format('d/m/Y').'. Envie um certificado válido na empresa.';
            }
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

    private function validadeCertificado(Empresa $empresa): ?Carbon
    {
        try {
            $certificado = Certificate::readPfx(
                (string) file_get_contents(storage_path('app/'.$empresa->certificado_path)),
                (string) $empresa->certificado_senha_decrypted
            );

            return Carbon::instance($certificado->getValidTo());
        } catch (Throwable) {
            return null;
        }
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

        $empresa = Empresa::query()->findOrFail($venda->empresa_id);

        $nfe = DB::transaction(function () use ($venda, $empresa) {
            $venda = Venda::query()->whereKey($venda->id)->lockForUpdate()->firstOrFail();

            if ($venda->situacao !== 'confirmada') {
                return 'Apenas vendas confirmadas podem ter NF-e emitida.';
            }

            $nfes = Nfe::query()
                ->where('empresa_id', $venda->empresa_id)
                ->where('venda_id', $venda->id)
                ->orderByDesc('id')
                ->get();

            if ($nfes->contains('situacao', 'autorizada')) {
                return 'Esta venda já possui NF-e autorizada.';
            }

            if ($nfes->contains('situacao', 'pendente')) {
                return 'Esta venda já possui uma NF-e em andamento. Use "Consultar SEFAZ" na NF-e pendente.';
            }

            $ambiente = (int) ($empresa->nfe_ambiente ?: 2);
            $serie = $empresa->nfe_serie ?: '001';
            $ultima = $nfes->first();

            // Nota rejeitada não consome o número na SEFAZ: reaproveita para não abrir buraco na numeração.
            // 539 = o número já foi usado por outra chave na SEFAZ, então não pode ser reaproveitado.
            if ($ultima
                && $ultima->situacao === 'rejeitada'
                && (int) $ultima->ambiente === $ambiente
                && $ultima->serie === $serie
                && $ultima->codigo_status !== '539') {
                $ultima->update([
                    'situacao' => 'pendente',
                    'chave_acesso' => null,
                    'xml_assinado' => null,
                    'xml_autorizado' => null,
                    'protocolo' => null,
                    'recibo' => null,
                    'codigo_status' => null,
                    'motivo_status' => null,
                    'data_autorizacao' => null,
                ]);
                $venda->update(['nfe_numero' => $ultima->numero]);

                return $ultima;
            }

            $numero = $this->empresaService->proximoNumeronNfe($empresa);

            $venda->update(['nfe_numero' => $numero]);

            return Nfe::query()->create([
                'empresa_id' => $empresa->id,
                'venda_id' => $venda->id,
                'numero' => $numero,
                'serie' => $serie,
                'situacao' => 'pendente',
                'ambiente' => $ambiente,
            ]);
        });

        if (is_string($nfe)) {
            return back()->with('error', $nfe);
        }

        $venda->refresh();

        try {
            $builder = new NfeBuilderService($empresa);
            $signedXml = $builder->buildAndSign($venda->load(['itens.produto.unidadeMedida', 'cliente']));

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

        $resultado = (new NfeSenderService($empresa, $builder->tools()))->enviar($signedXml, $empresa);

        return $this->aplicarResultadoEnvio($nfe, $resultado);
    }

    public function consultar(Nfe $nfe)
    {
        $this->autorizarNfe($nfe);

        if ($nfe->situacao !== 'pendente') {
            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'Somente NF-e pendente pode ser consultada para atualizar a situação.');
        }

        if (blank($nfe->chave_acesso) || blank($nfe->xml_assinado)) {
            // Sem XML assinado a nota nunca foi transmitida.
            $nfe->update([
                'situacao' => 'rejeitada',
                'motivo_status' => 'A NF-e não chegou a ser transmitida à SEFAZ.',
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'A NF-e não chegou a ser transmitida. Você pode emitir novamente pela venda.');
        }

        $empresa = Empresa::query()->findOrFail($nfe->empresa_id);

        try {
            $sender = new NfeSenderService($empresa);
        } catch (RuntimeException $e) {
            return redirect()->route('nfe.show', $nfe)->with('error', $e->getMessage());
        }

        $resultado = $sender->consultar($nfe->chave_acesso, $nfe->xml_assinado, (int) $nfe->ambiente);

        return $this->aplicarResultadoEnvio($nfe, $resultado);
    }

    public function xml(Nfe $nfe, bool $cancelamento = false)
    {
        $this->autorizarNfe($nfe);

        $conteudo = $cancelamento ? $nfe->xml_cancelamento : $nfe->xml_autorizado;

        if (blank($conteudo)) {
            return back()->with('error', 'XML não disponível para esta NF-e.');
        }

        $nome = ($nfe->chave_acesso ?: $nfe->numero).($cancelamento ? '-procEventoNFe' : '-nfe').'.xml';

        return response($conteudo, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$nome.'"',
        ]);
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

        try {
            $sender = new NfeSenderService($empresa);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $resultado = $sender->cancelar($nfe->chave_acesso, $justificativa, $nfe->protocolo, (int) $nfe->ambiente);

        if (($resultado['status'] ?? '') === 'cancelada') {
            $nfe->update([
                'situacao' => 'cancelada',
                'codigo_status' => $resultado['codigo'] ?? '101',
                'motivo_status' => mb_substr((string) ($resultado['motivo'] ?? 'Cancelamento homologado'), 0, 255),
                'xml_cancelamento' => $resultado['xml_evento'] ?? null,
                'data_cancelamento' => now(),
            ]);

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
        $motivo = (string) ($resultado['motivo'] ?? 'erro desconhecido');
        $codigo = $resultado['codigo'] ?? null;

        if ($status === 'autorizada' || $status === 'denegada') {
            $nfe->update([
                'situacao' => $status,
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'protocolo' => $resultado['protocolo'] ?? null,
                'xml_autorizado' => $resultado['xml_autorizado'] ?? null,
                'codigo_status' => $codigo ?? '100',
                'motivo_status' => mb_substr($status === 'autorizada' && ! isset($resultado['motivo']) ? 'Autorizado o uso da NF-e' : $motivo, 0, 255),
                'data_autorizacao' => now(),
            ]);

            if ($status === 'denegada') {
                return redirect()
                    ->route('nfe.show', $nfe)
                    ->with('error', 'NF-e denegada pela SEFAZ: '.$motivo.($codigo ? ' Código: '.$codigo : '').'. O número foi consumido e não pode ser reutilizado.');
            }

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('success', 'NF-e autorizada com sucesso.');
        }

        if ($status === 'rejeitada' || $status === 'nao_encontrada') {
            $nfe->update([
                'situacao' => 'rejeitada',
                'chave_acesso' => $resultado['chave'] ?? $nfe->chave_acesso,
                'codigo_status' => $codigo,
                'motivo_status' => mb_substr($status === 'nao_encontrada'
                    ? 'A NF-e não consta na SEFAZ (não foi autorizada). Pode ser emitida novamente.'
                    : $motivo, 0, 255),
            ]);

            return redirect()
                ->route('nfe.show', $nfe)
                ->with('error', 'NF-e rejeitada: '.$motivo.($codigo ? ' Código: '.$codigo : ''));
        }

        // Timeout, lote em processamento, retorno ilegível, duplicidade: a situação real na SEFAZ é desconhecida.
        // A nota continua pendente para impedir reemissão com outro número antes de consultar.
        $nfe->update([
            'codigo_status' => $codigo,
            'motivo_status' => mb_substr($motivo, 0, 255),
            'recibo' => $resultado['recibo'] ?? $nfe->recibo,
        ]);

        return redirect()
            ->route('nfe.show', $nfe)
            ->with('warning', 'Não foi possível confirmar a situação da NF-e na SEFAZ: '.$motivo.' Use "Consultar SEFAZ" antes de tentar emitir novamente.');
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
