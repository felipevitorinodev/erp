<?php

namespace App\Http\Controllers\Relatorios;

use App\Http\Controllers\Controller;
use App\Services\Relatorios\RelatorioRegistro;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    public function __construct(
        protected RelatorioRegistro $registro
    ) {}

    public function pagina(Request $request, string $relatorio): View
    {
        return $this->registro->obter($relatorio)->pagina($request);
    }

    public function resumo(Request $request, string $relatorio): JsonResponse
    {
        return $this->json($request, $relatorio, 'resumo');
    }

    public function dados(Request $request, string $relatorio): JsonResponse
    {
        return $this->json($request, $relatorio, 'dados');
    }

    public function csv(Request $request, string $relatorio): StreamedResponse
    {
        return $this->registro->obter($relatorio)->csv($request);
    }

    public function pdf(Request $request, string $relatorio): Response
    {
        return $this->registro->obter($relatorio)->pdf($request);
    }

    private function json(Request $request, string $relatorio, string $metodo): JsonResponse
    {
        try {
            return response()->json($this->registro->obter($relatorio)->{$metodo}($request));
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'Os filtros informados são inválidos.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }
}
