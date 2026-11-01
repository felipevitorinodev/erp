<?php

namespace App\Services\Relatorios;

use App\Models\Clientes\Cliente;
use App\Models\Fornecedores\Fornecedor;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\FaixaAtraso;
use App\Relatorios\PdfRelatorio;
use App\Repositories\Relatorios\InadimplenciaRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InadimplenciaRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected InadimplenciaRelatorioRepository $repository,
        protected string $tipo
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $consulta = $this->repository->consulta($this->tipo, $empresaId, $filtros);
        $totais = $this->repository->totais($consulta);
        $contas = $this->repository->ordenar(clone $consulta, $filtros)
            ->paginate(20)
            ->withQueryString();

        return view('relatorios.consulta', array_merge(
            $this->shell($this->slug(), $filtros, false, ['faixa', 'pessoa_id', 'busca']),
            [
                'parcial' => 'relatorios.parcial.inadimplencia',
                'totais' => $totais,
                'contas' => $contas,
                'pessoa' => $this->tipo === 'pagar' ? 'Fornecedor' : 'Cliente',
                'relacao' => $this->tipo === 'pagar' ? 'fornecedor' : 'cliente',
                'kpis' => $this->kpis($totais),
                'campos' => $this->campos($empresaId, $filtros),
            ]
        ));
    }

    /**
     * @return array{graficos: list<array<string, mixed>>}
     */
    public function resumo(Request $request): array
    {
        $filtros = $this->validar($request);
        $consulta = $this->repository->consulta($this->tipo, $this->empresaId(), $filtros);
        $totais = $this->repository->totais(clone $consulta);
        $fatias = [];
        foreach (FaixaAtraso::FAIXAS as $faixa) {
            $fatias[] = [
                'nome' => FaixaAtraso::rotulo($faixa),
                'total' => $totais['faixas'][$faixa]['saldo'],
            ];
        }

        return [
            'graficos' => [
                $this->graficoBarras('faixas', 'Saldo por faixa de atraso', $fatias, 'moeda'),
                $this->graficoLinha('vencimentos', 'Saldo por mês de vencimento', $this->repository->serie($consulta), 'Saldo'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $consulta = $this->repository->consulta($this->tipo, $this->empresaId(), $filtros);
        $totais = $this->repository->totais(clone $consulta);
        $relacao = $this->tipo === 'pagar' ? 'fornecedor' : 'cliente';
        $contas = $this->repository->ordenar(clone $consulta, $filtros)->paginate(20);

        return [
            'total' => $contas->total(),
            'totais' => $totais,
            'linhas' => $contas->getCollection()->map(function ($conta) use ($relacao) {
                $dias = FaixaAtraso::dias($conta->data_vencimento);

                return [
                    'id' => $conta->id,
                    'descricao' => $conta->descricao,
                    'pessoa' => $conta->{$relacao}->nome ?? null,
                    'vencimento' => $conta->data_vencimento->format('d/m/Y'),
                    'valor' => round((float) $conta->valor, 2),
                    'pago' => round((float) $conta->valor_pago, 2),
                    'saldo' => round((float) $conta->valor - (float) $conta->valor_pago, 2),
                    'dias' => $dias,
                    'faixa' => FaixaAtraso::nome($dias),
                    'situacao' => $conta->situacao,
                ];
            })->values(),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $relacao = $this->tipo === 'pagar' ? 'fornecedor' : 'cliente';
        $rotulo = $this->tipo === 'pagar' ? 'Fornecedor' : 'Cliente';
        $query = $this->repository->ordenar(
            $this->repository->consulta($this->tipo, $this->empresaId(), $filtros),
            $filtros
        );

        return CsvRelatorio::stream(
            'relatorio-inadimplencia-'.$this->tipo.'-'.date('Y-m-d').'.csv',
            ['Vencimento', $rotulo, 'Descrição', 'Valor', 'Pago', 'Saldo', 'Dias', 'Faixa', 'Situação'],
            $query->lazy(500)->map(function ($conta) use ($relacao) {
                $dias = FaixaAtraso::dias($conta->data_vencimento);
                $saldo = round((float) $conta->valor - (float) $conta->valor_pago, 2);

                return [
                    $conta->data_vencimento->format('d/m/Y'),
                    $conta->{$relacao}->nome ?? '—',
                    $conta->descricao,
                    number_format((float) $conta->valor, 2, ',', '.'),
                    number_format((float) $conta->valor_pago, 2, ',', '.'),
                    number_format($saldo, 2, ',', '.'),
                    $dias,
                    FaixaAtraso::rotulo(FaixaAtraso::nome($dias)),
                    $conta->situacao,
                ];
            })
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $consulta = $this->repository->consulta($this->tipo, $empresaId, $filtros);
        $totais = $this->repository->totais(clone $consulta);
        $relacao = $this->tipo === 'pagar' ? 'fornecedor' : 'cliente';
        $rotulo = $this->tipo === 'pagar' ? 'Fornecedor' : 'Cliente';
        $contas = $this->repository->ordenar(clone $consulta, $filtros)->limit(PdfRelatorio::LIMITE)->get();
        $aviso = PdfRelatorio::aviso($totais['qtd']);
        $resumo = ['Posição em '.now()->format('d/m/Y')];
        if ($filtros['faixa'] !== '') {
            $resumo[] = 'Faixa: '.FaixaAtraso::rotulo($filtros['faixa']);
        }
        if ($aviso) {
            $resumo[] = $aviso;
        }

        return PdfRelatorio::stream('relatorio-inadimplencia-'.$this->tipo.'-'.date('Y-m-d').'.pdf', [
            'titulo' => $this->tipo === 'pagar' ? 'Inadimplência a pagar' : 'Inadimplência a receber',
            'periodo' => 'Posição em '.now()->format('d/m/Y'),
            'resumo' => implode(' · ', $resumo),
            'secao' => 'Títulos vencidos',
            'colunas' => [
                ['label' => 'Vencimento', 'width' => '12%'],
                ['label' => $rotulo, 'width' => '20%'],
                ['label' => 'Descrição', 'width' => '22%'],
                ['label' => 'Saldo', 'align' => 'right', 'width' => '14%'],
                ['label' => 'Dias', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Faixa', 'width' => '14%'],
                ['label' => 'Situação', 'width' => '8%'],
            ],
            'linhas' => $contas->map(function ($conta) use ($relacao) {
                $dias = FaixaAtraso::dias($conta->data_vencimento);

                return [
                    $conta->data_vencimento->format('d/m/Y'),
                    $conta->{$relacao}->nome ?? '—',
                    $conta->descricao ?: '—',
                    $this->moeda(round((float) $conta->valor - (float) $conta->valor_pago, 2)),
                    (string) $dias,
                    FaixaAtraso::rotulo(FaixaAtraso::nome($dias)),
                    $conta->situacao === 'parcial' ? 'Parcial' : 'Aberta',
                ];
            })->all(),
            'totais' => [
                ['label' => 'Títulos', 'valor' => (string) $totais['qtd']],
                ['label' => '1–30 dias', 'valor' => $this->moeda($totais['faixas']['1-30']['saldo'])],
                ['label' => '31–60 dias', 'valor' => $this->moeda($totais['faixas']['31-60']['saldo'])],
                ['label' => '61–90 dias', 'valor' => $this->moeda($totais['faixas']['61-90']['saldo'])],
                ['label' => '91+ dias', 'valor' => $this->moeda($totais['faixas']['91+']['saldo'])],
                ['label' => 'Saldo vencido', 'valor' => $this->moeda($totais['saldo']), 'destaque' => true],
            ],
        ], 'landscape');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $dados = $request->validate([
            'faixa' => ['nullable', 'in:1-30,31-60,61-90,91+'],
            'pessoa_id' => ['nullable', 'integer'],
            'busca' => ['nullable', 'string', 'max:120'],
            'ordem' => ['nullable', 'in:vencimento,valor,saldo'],
            'direcao' => ['nullable', 'in:asc,desc'],
        ]);

        $modelo = $this->tipo === 'pagar' ? Fornecedor::class : Cliente::class;

        return [
            'faixa' => $dados['faixa'] ?? '',
            'pessoa_id' => $this->pertence($modelo, $this->empresaId(), isset($dados['pessoa_id']) ? (int) $dados['pessoa_id'] : null),
            'busca' => $dados['busca'] ?? '',
            'ordem' => $dados['ordem'] ?? '',
            'direcao' => $dados['direcao'] ?? '',
        ];
    }

    /**
     * @param  array{saldo: float, qtd: int, faixas: array<string, array{qtd: int, saldo: float}>}  $totais
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais): array
    {
        $cards = [
            [
                'label' => 'Saldo vencido',
                'valor' => $this->moeda($totais['saldo']),
                'detalhe' => $totais['qtd'].($totais['qtd'] === 1 ? ' título em atraso' : ' títulos em atraso'),
                'tom' => 'danger',
                'icone' => 'alert-triangle',
                'destaque' => true,
            ],
            [
                'label' => 'Títulos',
                'valor' => (string) $totais['qtd'],
                'detalhe' => 'Em aberto com vencimento anterior a hoje',
                'tom' => 'primary',
                'icone' => 'layers',
                'destaque' => false,
            ],
        ];

        foreach (FaixaAtraso::FAIXAS as $faixa) {
            $item = $totais['faixas'][$faixa];
            $cards[] = [
                'label' => FaixaAtraso::rotulo($faixa),
                'valor' => $this->moeda($item['saldo']),
                'detalhe' => $item['qtd'].($item['qtd'] === 1 ? ' título' : ' títulos'),
                'tom' => 'warning',
                'icone' => 'history',
                'destaque' => false,
            ];
        }

        return $cards;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    private function campos(int $empresaId, array $filtros): array
    {
        $pagar = $this->tipo === 'pagar';
        $modelo = $pagar ? Fornecedor::class : Cliente::class;

        return [
            [
                'tipo' => 'select',
                'nome' => 'faixa',
                'rotulo' => 'Faixa de atraso',
                'valor' => $filtros['faixa'],
                'opcoes' => [
                    '1-30' => '1–30 dias',
                    '31-60' => '31–60 dias',
                    '61-90' => '61–90 dias',
                    '91+' => '91+ dias',
                ],
                'vazio' => 'Todas',
            ],
            [
                'tipo' => 'busca',
                'nome' => 'pessoa_id',
                'rotulo' => $pagar ? 'Fornecedor' : 'Cliente',
                'valor' => $filtros['pessoa_id'],
                'texto' => $filtros['pessoa_id']
                    ? (string) $modelo::query()->where('empresa_id', $empresaId)->whereKey($filtros['pessoa_id'])->value('nome')
                    : '',
                'url' => route($pagar ? 'relatorio.opcoes.fornecedores' : 'relatorio.opcoes.clientes'),
                'placeholder' => $pagar ? 'Nome do fornecedor' : 'Nome do cliente',
            ],
            [
                'tipo' => 'texto',
                'nome' => 'busca',
                'rotulo' => 'Busca',
                'valor' => $filtros['busca'],
                'placeholder' => 'Descrição ou nome',
            ],
        ];
    }

    private function slug(): string
    {
        return $this->tipo === 'pagar' ? 'inadimplencia-pagar' : 'inadimplencia-receber';
    }
}
