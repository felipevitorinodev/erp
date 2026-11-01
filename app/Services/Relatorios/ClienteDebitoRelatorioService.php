<?php

namespace App\Services\Relatorios;

use App\Models\Clientes\Cliente;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\PdfRelatorio;
use App\Repositories\Relatorios\ClienteDebitoRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClienteDebitoRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected ClienteDebitoRelatorioRepository $repository
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $linhas = $this->linhas($this->empresaId(), $filtros);
        $totais = $this->totais($linhas);

        return view('relatorios.consulta', array_merge(
            $this->shell('clientes-com-debito', $filtros, false, ['cliente_id', 'busca']),
            [
                'parcial' => 'relatorios.parcial.clientes-debito',
                'totais' => $totais,
                'debitos' => $this->paginarLista($linhas)->withQueryString(),
                'kpis' => $this->kpis($totais),
                'campos' => $this->campos($this->empresaId(), $filtros),
            ]
        ));
    }

    /**
     * @return array{graficos: list<array<string, mixed>>}
     */
    public function resumo(Request $request): array
    {
        $linhas = $this->linhas($this->empresaId(), $this->validar($request));
        $porSaldo = array_slice($linhas, 0, 8);
        $porVencido = $linhas;
        usort($porVencido, fn (array $a, array $b) => $b['vencido'] <=> $a['vencido']);
        $porVencido = array_slice($porVencido, 0, 8);

        return [
            'graficos' => [
                $this->graficoBarras('saldo', 'Maior saldo em aberto', array_map(
                    fn (array $linha) => ['nome' => $linha['nome'], 'total' => $linha['saldo']],
                    $porSaldo
                ), 'moeda'),
                $this->graficoBarras('vencido', 'Maior saldo vencido', array_map(
                    fn (array $linha) => ['nome' => $linha['nome'], 'total' => $linha['vencido']],
                    $porVencido
                ), 'moeda'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $linhas = $this->linhas($this->empresaId(), $this->validar($request));
        $pagina = $this->paginarLista($linhas);

        return [
            'total' => $pagina->total(),
            'totais' => $this->totais($linhas),
            'linhas' => array_values($pagina->items()),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $linhas = $this->linhas($this->empresaId(), $this->validar($request));

        return CsvRelatorio::stream(
            'relatorio-clientes-com-debito-'.date('Y-m-d').'.csv',
            ['Cliente', 'Títulos', 'Saldo em aberto', 'Saldo vencido'],
            array_map(fn (array $linha) => [
                $linha['nome'],
                $linha['qtd'],
                number_format($linha['saldo'], 2, ',', '.'),
                number_format($linha['vencido'], 2, ',', '.'),
            ], $linhas)
        );
    }

    public function pdf(Request $request): Response
    {
        $linhas = $this->linhas($this->empresaId(), $this->validar($request));
        $totais = $this->totais($linhas);
        $aviso = PdfRelatorio::aviso(count($linhas));

        return PdfRelatorio::stream('relatorio-clientes-com-debito-'.date('Y-m-d').'.pdf', [
            'titulo' => 'Clientes com débito',
            'periodo' => 'Posição em '.now()->format('d/m/Y'),
            'resumo' => $aviso ?: 'Títulos em aberto, sem recorte de período',
            'secao' => 'Clientes',
            'colunas' => [
                ['label' => 'Cliente', 'width' => '40%'],
                ['label' => 'Títulos', 'align' => 'right', 'width' => '15%'],
                ['label' => 'Saldo em aberto', 'align' => 'right', 'width' => '22%'],
                ['label' => 'Saldo vencido', 'align' => 'right', 'width' => '23%'],
            ],
            'linhas' => array_map(fn (array $linha) => [
                $linha['nome'],
                (string) $linha['qtd'],
                $this->moeda($linha['saldo']),
                $this->moeda($linha['vencido']),
            ], array_slice($linhas, 0, PdfRelatorio::LIMITE)),
            'totais' => [
                ['label' => 'Clientes', 'valor' => (string) $totais['qtd_clientes']],
                ['label' => 'Títulos', 'valor' => (string) $totais['qtd']],
                ['label' => 'Saldo vencido', 'valor' => $this->moeda($totais['vencido'])],
                ['label' => 'Saldo em aberto', 'valor' => $this->moeda($totais['saldo']), 'destaque' => true],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'cliente_id' => ['nullable', 'integer'],
            'busca' => ['nullable', 'string', 'max:120'],
        ]);

        return [
            'cliente_id' => $this->pertence(Cliente::class, $this->empresaId(), isset($dados['cliente_id']) ? (int) $dados['cliente_id'] : null),
            'busca' => $dados['busca'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function linhas(int $empresaId, array $filtros): array
    {
        $brutas = $this->repository->linhas($this->repository->consulta($empresaId, $filtros));
        $ids = array_values(array_filter(array_column($brutas, 'cliente_id')));
        $nomes = $ids === []
            ? collect()
            : Cliente::withTrashed()->where('empresa_id', $empresaId)->whereIn('id', $ids)->pluck('nome', 'id');

        $linhas = [];
        foreach ($brutas as $linha) {
            $linhas[] = [
                'cliente_id' => $linha['cliente_id'],
                'nome' => $linha['cliente_id']
                    ? (string) ($nomes[$linha['cliente_id']] ?? 'Cliente #'.$linha['cliente_id'])
                    : 'Sem cliente',
                'qtd' => $linha['qtd'],
                'saldo' => $linha['saldo'],
                'vencido' => $linha['vencido'],
            ];
        }

        if ($filtros['busca'] !== '') {
            $termo = mb_strtolower($filtros['busca']);
            $linhas = array_values(array_filter(
                $linhas,
                fn (array $linha) => str_contains(mb_strtolower((string) $linha['nome']), $termo)
            ));
        }

        usort($linhas, function (array $a, array $b): int {
            $saldo = $b['saldo'] <=> $a['saldo'];

            return $saldo !== 0 ? $saldo : strcasecmp((string) $a['nome'], (string) $b['nome']);
        });

        return $linhas;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @return array<string, int|float>
     */
    private function totais(array $linhas): array
    {
        return [
            'saldo' => round((float) array_sum(array_column($linhas, 'saldo')), 2),
            'vencido' => round((float) array_sum(array_column($linhas, 'vencido')), 2),
            'qtd_clientes' => count($linhas),
            'qtd' => (int) array_sum(array_column($linhas, 'qtd')),
        ];
    }

    /**
     * @param  array<string, int|float>  $totais
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais): array
    {
        return [
            [
                'label' => 'Saldo em aberto',
                'valor' => $this->moeda($totais['saldo']),
                'detalhe' => $totais['qtd'].($totais['qtd'] === 1 ? ' título' : ' títulos'),
                'tom' => 'primary',
                'icone' => 'dollar',
                'destaque' => true,
            ],
            [
                'label' => 'Saldo vencido',
                'valor' => $this->moeda($totais['vencido']),
                'detalhe' => 'Vencimento anterior a hoje',
                'tom' => 'danger',
                'icone' => 'alert-triangle',
                'destaque' => false,
            ],
            [
                'label' => 'Clientes',
                'valor' => (string) $totais['qtd_clientes'],
                'detalhe' => 'Com saldo em aberto',
                'tom' => 'warning',
                'icone' => 'bar-chart',
                'destaque' => false,
            ],
            [
                'label' => 'Títulos',
                'valor' => (string) $totais['qtd'],
                'detalhe' => 'Abertos ou parciais',
                'tom' => 'primary',
                'icone' => 'layers',
                'destaque' => false,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function campos(int $empresaId, array $filtros): array
    {
        return [
            [
                'tipo' => 'busca',
                'nome' => 'cliente_id',
                'rotulo' => 'Cliente',
                'valor' => $filtros['cliente_id'],
                'texto' => $filtros['cliente_id']
                    ? (string) Cliente::query()->where('empresa_id', $empresaId)->whereKey($filtros['cliente_id'])->value('nome')
                    : '',
                'url' => route('relatorio.opcoes.clientes'),
                'placeholder' => 'Nome do cliente',
            ],
            [
                'tipo' => 'texto',
                'nome' => 'busca',
                'rotulo' => 'Busca',
                'valor' => $filtros['busca'],
                'placeholder' => 'Nome do cliente',
            ],
        ];
    }
}
