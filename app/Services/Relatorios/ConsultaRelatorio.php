<?php

namespace App\Services\Relatorios;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface ConsultaRelatorio
{
    public function pagina(Request $request): View;

    /**
     * @return array<string, mixed>
     */
    public function resumo(Request $request): array;

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array;

    public function csv(Request $request): StreamedResponse;

    public function pdf(Request $request): Response;
}
