<?php

namespace App\Http\Controllers;

use App\Models\ContaPagar;
use App\Models\ContaReceber;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FluxoCaixaController extends Controller
{
    public function index(Request $request)
    {
        $dados = $this->montarPeriodo($request);

        $page = max(1, (int) $request->get('page', 1));
        $perPage = 20;
        $diasPagina = array_slice($dados['dias'], ($page - 1) * $perPage, $perPage);

        $diasPaginados = new LengthAwarePaginator(
            $diasPagina,
            count($dados['dias']),
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('fluxo-de-caixa.index', [
            'dias'           => $diasPaginados,
            'total_entradas' => $dados['total_entradas'],
            'total_saidas'   => $dados['total_saidas'],
            'saldo_periodo'  => $dados['total_entradas'] - $dados['total_saidas'],
            'filtros'        => $dados['filtros'],
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $dados = $this->montarPeriodo($request);
        $filename = 'fluxo-caixa-' . $dados['filtros']['data_inicio'] . '_' . $dados['filtros']['data_fim'] . '.csv';

        return response()->streamDownload(function () use ($dados) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Data', 'Entradas', 'Saídas', 'Saldo do Dia'], ';');

            foreach ($dados['dias'] as $dia) {
                fputcsv($out, [
                    $dia['data']->format('d/m/Y'),
                    number_format($dia['entradas'], 2, ',', '.'),
                    number_format($dia['saidas'], 2, ',', '.'),
                    number_format($dia['saldo_dia'], 2, ',', '.'),
                ], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, [
                'Totais',
                number_format($dados['total_entradas'], 2, ',', '.'),
                number_format($dados['total_saidas'], 2, ',', '.'),
                number_format($dados['total_entradas'] - $dados['total_saidas'], 2, ',', '.'),
            ], ';');

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function montarPeriodo(Request $request): array
    {
        $empresaId = auth()->user()->empresa_id ?? 0;

        $dataInicio = $request->filled('data_inicio')
            ? Carbon::parse($request->data_inicio)->startOfDay()
            : now()->startOfMonth()->startOfDay();

        $dataFim = $request->filled('data_fim')
            ? Carbon::parse($request->data_fim)->startOfDay()
            : now()->startOfDay();

        if ($dataFim->lt($dataInicio)) {
            [$dataInicio, $dataFim] = [$dataFim->copy(), $dataInicio->copy()];
        }

        if ($dataInicio->diffInDays($dataFim) > 90) {
            $dataFim = $dataInicio->copy()->addDays(90);
        }

        $entradasRaw = ContaReceber::where('empresa_id', $empresaId)
            ->whereIn('situacao', ['paga', 'parcial'])
            ->whereNotNull('data_pagamento')
            ->whereDate('data_pagamento', '>=', $dataInicio->toDateString())
            ->whereDate('data_pagamento', '<=', $dataFim->toDateString())
            ->selectRaw('DATE(data_pagamento) as dia, SUM(valor_pago) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $saidasRaw = ContaPagar::where('empresa_id', $empresaId)
            ->whereIn('situacao', ['paga', 'parcial'])
            ->whereNotNull('data_pagamento')
            ->whereDate('data_pagamento', '>=', $dataInicio->toDateString())
            ->whereDate('data_pagamento', '<=', $dataFim->toDateString())
            ->selectRaw('DATE(data_pagamento) as dia, SUM(valor_pago) as total')
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $dias = [];
        $cursor = $dataInicio->copy();
        $totalEntradas = 0.0;
        $totalSaidas = 0.0;

        while ($cursor->lte($dataFim)) {
            $chave = $cursor->toDateString();
            $entradas = (float) ($entradasRaw[$chave] ?? 0);
            $saidas = (float) ($saidasRaw[$chave] ?? 0);

            $dias[] = [
                'data'      => $cursor->copy(),
                'entradas'  => $entradas,
                'saidas'    => $saidas,
                'saldo_dia' => $entradas - $saidas,
            ];

            $totalEntradas += $entradas;
            $totalSaidas += $saidas;
            $cursor->addDay();
        }

        return [
            'dias'           => $dias,
            'total_entradas' => $totalEntradas,
            'total_saidas'   => $totalSaidas,
            'filtros'        => [
                'data_inicio' => $dataInicio->toDateString(),
                'data_fim'    => $dataFim->toDateString(),
            ],
        ];
    }
}
