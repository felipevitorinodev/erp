<?php

namespace App\Services\Relatorios;

use App\Models\CategoriasFinanceiras\CategoriaFinanceira;
use App\Models\Clientes\Cliente;
use App\Models\Fornecedores\Fornecedor;
use App\Relatorios\CsvRelatorio;
use App\Relatorios\PdfRelatorio;
use App\Relatorios\Periodo;
use App\Relatorios\SerieTemporal;
use App\Repositories\Relatorios\ContaRelatorioRepository;
use App\Services\Relatorios\Concerns\MontaRelatorio;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContaRelatorioService implements ConsultaRelatorio
{
    use MontaRelatorio;

    public function __construct(
        protected ContaRelatorioRepository $repository,
        protected string $tipo
    ) {}

    public function pagina(Request $request): View
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $totais = $this->repository->totais($this->repository->consulta($this->tipo, $empresaId, $filtros['periodo'], $filtros));
        $anterior = $this->repository->totais($this->repository->consulta($this->tipo, $empresaId, $filtros['periodo']->anterior(), $filtros));
        $contas = $this->repository->ordenar($this->repository->consulta($this->tipo, $empresaId, $filtros['periodo'], $filtros), $filtros)
            ->paginate(20, ['*'], 'page', null, $totais['registros'])
            ->withQueryString();

        return view('relatorios.consulta', array_merge(
            $this->shell($this->slug(), $filtros, true, ['situacao', 'categoria_id', 'vencimento', 'busca', 'pessoa_id']),
            [
                'parcial' => 'relatorios.parcial.contas',
                'totais' => $totais,
                'soma_valor' => $totais['soma_valor'],
                'soma_pago' => $totais['soma_pago'],
                'subtotais_categoria' => $totais['subtotais'],
                'contas' => $contas,
                'pessoa' => $this->tipo === 'pagar' ? 'Fornecedor' : 'Cliente',
                'relacao' => $this->tipo === 'pagar' ? 'fornecedor' : 'cliente',
                'rotuloPago' => $this->tipo === 'pagar' ? 'Pago' : 'Recebido',
                'rotuloAberto' => $this->tipo === 'pagar' ? 'A pagar' : 'A receber',
                'kpis' => $this->kpis($totais, $anterior),
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
        $empresaId = $this->empresaId();
        $periodo = $filtros['periodo'];
        $totais = $this->repository->totais($this->repository->consulta($this->tipo, $empresaId, $periodo, $filtros));
        $serie = SerieTemporal::preencher(
            $periodo,
            $this->repository->serie($this->repository->consulta($this->tipo, $empresaId, $periodo, $filtros), $periodo)
        );

        return [
            'graficos' => [
                $this->graficoLinha('evolucao', 'Valor por vencimento', $serie, 'Valor'),
                $this->graficoBarras('categorias', 'Valor por categoria', $this->repository->categoriasGrafico($totais['subtotais']), 'moeda'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dados(Request $request): array
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $totais = $this->repository->totais($this->repository->consulta($this->tipo, $empresaId, $filtros['periodo'], $filtros));
        $contas = $this->repository->ordenar($this->repository->consulta($this->tipo, $empresaId, $filtros['periodo'], $filtros), $filtros)
            ->paginate(20, ['*'], 'page', null, $totais['registros']);
        $relacao = $this->tipo === 'pagar' ? 'fornecedor' : 'cliente';

        return [
            'total' => $contas->total(),
            'totais' => [
                'registros' => $totais['registros'],
                'soma_valor' => $totais['soma_valor'],
                'soma_pago' => $totais['soma_pago'],
                'saldo_aberto' => $totais['saldo_aberto'],
                'saldo_vencido' => $totais['saldo_vencido'],
            ],
            'linhas' => $contas->getCollection()->map(fn ($conta) => [
                'id' => $conta->id,
                'descricao' => $conta->descricao,
                'pessoa' => $conta->{$relacao}->nome ?? null,
                'categoria' => $conta->categoria->nome ?? null,
                'vencimento' => $conta->data_vencimento->format('d/m/Y'),
                'valor' => round((float) $conta->valor, 2),
                'pago' => round((float) $conta->valor_pago, 2),
                'situacao' => $conta->situacao,
            ])->values(),
        ];
    }

    public function csv(Request $request): StreamedResponse
    {
        $filtros = $this->validar($request);
        $query = $this->repository->ordenar(
            $this->repository->consulta($this->tipo, $this->empresaId(), $filtros['periodo'], $filtros),
            $filtros
        );
        $relacao = $this->tipo === 'pagar' ? 'fornecedor' : 'cliente';
        $rotulo = $this->tipo === 'pagar' ? 'Fornecedor' : 'Cliente';

        return CsvRelatorio::stream(
            'relatorio-contas-'.$this->tipo.'-'.date('Y-m-d').'.csv',
            ['Descrição', $rotulo, 'Categoria', 'Vencimento', 'Valor', 'Pago', 'Situação'],
            $query->lazy(500)->map(fn ($conta) => [
                $conta->descricao,
                $conta->{$relacao}->nome ?? '—',
                $conta->categoria->nome ?? '—',
                $conta->data_vencimento->format('d/m/Y'),
                number_format((float) $conta->valor, 2, ',', '.'),
                number_format((float) $conta->valor_pago, 2, ',', '.'),
                $conta->situacao,
            ])
        );
    }

    public function pdf(Request $request): Response
    {
        $filtros = $this->validar($request);
        $empresaId = $this->empresaId();
        $periodo = $filtros['periodo'];
        $totais = $this->repository->totais($this->repository->consulta($this->tipo, $empresaId, $periodo, $filtros));
        $contas = $this->repository->ordenar($this->repository->consulta($this->tipo, $empresaId, $periodo, $filtros), $filtros)
            ->limit(PdfRelatorio::LIMITE)
            ->get();
        $relacao = $this->tipo === 'pagar' ? 'fornecedor' : 'cliente';
        $rotulo = $this->tipo === 'pagar' ? 'Fornecedor' : 'Cliente';
        $aviso = PdfRelatorio::aviso($totais['registros']);
        $situacoes = $this->situacoes();

        $resumo = ['Vencimento no período', 'Situação: '.($situacoes[$filtros['situacao']] ?? 'Todas')];
        if ($filtros['vencimento'] !== '') {
            $resumo[] = 'Filtro: '.($filtros['vencimento'] === 'vencidos' ? 'Vencidos' : 'A vencer');
        }
        if ($filtros['busca'] !== '') {
            $resumo[] = 'Busca: '.$filtros['busca'];
        }
        if ($aviso) {
            $resumo[] = $aviso;
        }

        $arquivo = $this->tipo === 'pagar' ? 'relatorio-contas-pagar-' : 'relatorio-contas-receber-';

        return PdfRelatorio::stream($arquivo.date('Y-m-d').'.pdf', [
            'titulo' => $this->tipo === 'pagar' ? 'Contas a pagar' : 'Contas a receber',
            'periodo' => $periodo->rotulo(),
            'resumo' => implode(' · ', $resumo),
            'secao' => 'Contas',
            'colunas' => [
                ['label' => 'Nº', 'width' => '8%'],
                ['label' => 'Descrição', 'width' => '22%'],
                ['label' => $rotulo, 'width' => '18%'],
                ['label' => 'Categoria', 'width' => '14%'],
                ['label' => 'Vencimento', 'width' => '12%'],
                ['label' => 'Valor', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Pago', 'align' => 'right', 'width' => '10%'],
                ['label' => 'Situação', 'width' => '6%'],
            ],
            'linhas' => $contas->map(fn ($conta) => [
                (string) $conta->id,
                $conta->descricao,
                $conta->{$relacao}->nome ?? '—',
                $conta->categoria->nome ?? '—',
                $conta->data_vencimento->format('d/m/Y'),
                'R$ '.number_format((float) $conta->valor, 2, ',', '.'),
                'R$ '.number_format((float) $conta->valor_pago, 2, ',', '.'),
                $situacoes[$conta->situacao] ?? $conta->situacao,
            ])->all(),
            'totais' => [
                ['label' => 'Soma do valor', 'valor' => $this->moeda($totais['soma_valor'])],
                ['label' => 'Saldo em aberto ('.$totais['qtd_aberto'].')', 'valor' => $this->moeda($totais['saldo_aberto'])],
                ['label' => 'Vencido ('.$totais['qtd_vencido'].')', 'valor' => $this->moeda($totais['saldo_vencido'])],
                ['label' => 'Soma do pago', 'valor' => $this->moeda($totais['soma_pago']), 'destaque' => true],
            ],
        ], 'landscape');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        $periodo = Periodo::extrair($request);
        $dados = $request->validate([
            'situacao' => ['nullable', 'in:em_aberto,pendente,aberta,parcial,paga,cancelada'],
            'categoria_id' => ['nullable', 'integer'],
            'pessoa_id' => ['nullable', 'integer'],
            'vencimento' => ['nullable', 'in:vencidos,a_vencer'],
            'busca' => ['nullable', 'string', 'max:120'],
            'ordem' => ['nullable', 'in:vencimento,valor,pago,descricao,situacao'],
            'direcao' => ['nullable', 'in:asc,desc'],
        ]);

        $empresaId = $this->empresaId();
        $categoriaId = null;
        if (! empty($dados['categoria_id'])) {
            $categoriaId = CategoriaFinanceira::query()
                ->where('empresa_id', $empresaId)
                ->where('tipo', $this->tipo === 'pagar' ? 'despesa' : 'receita')
                ->whereKey($dados['categoria_id'])
                ->exists() ? (int) $dados['categoria_id'] : null;
        }

        $modeloPessoa = $this->tipo === 'pagar' ? Fornecedor::class : Cliente::class;

        return [
            'periodo' => $periodo,
            'data_inicio' => $periodo->inicio,
            'data_fim' => $periodo->fim,
            'situacao' => $dados['situacao'] ?? '',
            'categoria_id' => $categoriaId,
            'pessoa_id' => $this->pertence($modeloPessoa, $empresaId, isset($dados['pessoa_id']) ? (int) $dados['pessoa_id'] : null),
            'vencimento' => $dados['vencimento'] ?? '',
            'busca' => $dados['busca'] ?? '',
            'ordem' => $dados['ordem'] ?? '',
            'direcao' => $dados['direcao'] ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $totais
     * @param  array<string, mixed>  $anterior
     * @return list<array<string, mixed>>
     */
    private function kpis(array $totais, array $anterior): array
    {
        $rotuloPago = $this->tipo === 'pagar' ? 'Pago' : 'Recebido';
        $rotuloAberto = $this->tipo === 'pagar' ? 'A pagar' : 'A receber';

        return [
            [
                'label' => 'Total no período',
                'valor' => $this->moeda($totais['soma_valor']),
                'detalhe' => $this->detalhe(
                    $totais['qtd'].($totais['qtd'] === 1 ? ' título' : ' títulos'),
                    $this->variacao((float) $totais['soma_valor'], (float) $anterior['soma_valor'])
                ),
                'tom' => 'primary',
                'icone' => 'dollar',
                'destaque' => false,
            ],
            [
                'label' => $rotuloPago,
                'valor' => $this->moeda($totais['soma_pago']),
                'detalhe' => number_format($totais['percentual_pago'], 1, ',', '.').'% do total',
                'tom' => 'success',
                'icone' => 'check',
                'destaque' => true,
            ],
            [
                'label' => $rotuloAberto,
                'valor' => $this->moeda($totais['saldo_aberto']),
                'detalhe' => $totais['qtd_aberto'].($totais['qtd_aberto'] === 1 ? ' título em aberto' : ' títulos em aberto'),
                'tom' => 'warning',
                'icone' => 'history',
                'destaque' => false,
            ],
            [
                'label' => 'Vencido',
                'valor' => $this->moeda($totais['saldo_vencido']),
                'detalhe' => $totais['qtd_vencido'].($totais['qtd_vencido'] === 1 ? ' título vencido' : ' títulos vencidos'),
                'tom' => 'danger',
                'icone' => 'alert-triangle',
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
        $modelo = $this->tipo === 'pagar' ? Fornecedor::class : Cliente::class;
        $rotulo = $this->tipo === 'pagar' ? 'Fornecedor' : 'Cliente';
        $url = $this->tipo === 'pagar' ? route('relatorio.opcoes.fornecedores') : route('relatorio.opcoes.clientes');

        return [
            [
                'tipo' => 'select',
                'nome' => 'situacao',
                'rotulo' => 'Situação',
                'valor' => $filtros['situacao'],
                'opcoes' => $this->situacoes(),
                'vazio' => 'Todas',
            ],
            [
                'tipo' => 'select',
                'nome' => 'categoria_id',
                'rotulo' => 'Categoria',
                'valor' => $filtros['categoria_id'] ?? '',
                'opcoes' => CategoriaFinanceira::query()
                    ->where('empresa_id', $empresaId)
                    ->where('tipo', $this->tipo === 'pagar' ? 'despesa' : 'receita')
                    ->where('ativo', true)
                    ->orderBy('nome')
                    ->pluck('nome', 'id')
                    ->all(),
                'vazio' => 'Todas',
            ],
            [
                'tipo' => 'select',
                'nome' => 'vencimento',
                'rotulo' => 'Vencimento',
                'valor' => $filtros['vencimento'],
                'opcoes' => ['vencidos' => 'Vencidos', 'a_vencer' => 'A vencer'],
                'vazio' => 'Todos',
            ],
            [
                'tipo' => 'busca',
                'nome' => 'pessoa_id',
                'rotulo' => $rotulo,
                'valor' => $filtros['pessoa_id'],
                'texto' => $filtros['pessoa_id'] ? (string) $modelo::query()->where('empresa_id', $empresaId)->whereKey($filtros['pessoa_id'])->value('nome') : '',
                'url' => $url,
                'placeholder' => 'Nome do '.mb_strtolower($rotulo),
            ],
            [
                'tipo' => 'texto',
                'nome' => 'busca',
                'rotulo' => 'Busca',
                'valor' => $filtros['busca'],
                'placeholder' => $this->tipo === 'pagar' ? 'Descrição ou fornecedor' : 'Descrição ou cliente',
            ],
        ];
    }

    private function slug(): string
    {
        return $this->tipo === 'pagar' ? 'contas-pagar' : 'contas-receber';
    }

    /**
     * @return array<string, string>
     */
    private function situacoes(): array
    {
        return [
            'em_aberto' => 'Em aberto',
            'aberta' => 'Aberta',
            'parcial' => 'Parcial',
            'paga' => 'Paga',
            'cancelada' => 'Cancelada',
        ];
    }
}
