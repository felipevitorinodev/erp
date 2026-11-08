<?php

namespace App\Http\Controllers\Nfces;

use App\Http\Controllers\Controller;
use App\Models\Empresas\Empresa;
use App\Models\Nfes\Nfe;
use App\Models\Vendas\Venda;
use App\Services\Nfces\NfceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** NFC-e pela tela da venda: emitir depois, consultar a pendente, cancelar, imprimir e baixar o XML. */
class NfceController extends Controller
{
    public function __construct(
        protected NfceService $nfceService
    ) {}

    public function danfe(Venda $venda)
    {
        return $this->nfceService->danfe($venda);
    }

    public function emitir(Request $request, Venda $venda): RedirectResponse
    {
        $dados = $request->validate(
            ['documento' => ['nullable', 'string', 'max:18']],
            ['documento.max' => 'O CPF ou CNPJ tem no máximo 18 caracteres.'],
        );

        return $this->voltarParaVenda((int) $venda->id, $this->nfceService->emitir($venda, $dados['documento'] ?? null));
    }

    public function consultar(Nfe $nfe): RedirectResponse
    {
        return $this->voltarParaVenda((int) $nfe->venda_id, $this->nfceService->consultar($nfe));
    }

    /** Cancelar a NFC-e segue a mesma permissão de cancelar uma venda confirmada. */
    public function cancelar(Request $request, Nfe $nfe): RedirectResponse
    {
        abort_unless($request->user()->pode('vendas.cancelar'), 403);

        $dados = $request->validate([
            'justificativa' => ['required', 'string', 'min:15', 'max:255'],
        ], [
            'justificativa.required' => 'Escreva o motivo do cancelamento.',
            'justificativa.min' => 'O motivo precisa ter pelo menos 15 caracteres (exigência da SEFAZ).',
            'justificativa.max' => 'O motivo pode ter no máximo 255 caracteres.',
        ]);

        return $this->voltarParaVenda((int) $nfe->venda_id, $this->nfceService->cancelar($nfe, trim($dados['justificativa'])));
    }

    public function xml(Request $request, Nfe $nfe)
    {
        return $this->nfceService->xml($nfe, $request->boolean('cancelamento'));
    }

    public function verificar(Empresa $empresa): RedirectResponse
    {
        abort_unless((int) $empresa->id === (int) auth()->user()->empresa_id, 404);

        return redirect()
            ->to(route('empresa.edit', $empresa).'#nfce')
            ->with('nfce_verificacao', $this->nfceService->verificar($empresa));
    }

    /** @param array{ok: bool, mensagem: string} $resultado */
    private function voltarParaVenda(int $vendaId, array $resultado): RedirectResponse
    {
        return redirect()
            ->to(route('venda.show', $vendaId).'#nota-fiscal')
            ->with($resultado['ok'] ? 'success' : 'error', $resultado['mensagem']);
    }
}
