@php
    $chips = [];
    if (! empty($periodo)) {
        $chips[] = ['rotulo' => 'Período', 'valor' => $periodo];
    }
    foreach (preg_split('/\s*·\s*/', (string) ($resumo ?? '')) ?: [] as $parte) {
        $parte = trim($parte);
        if ($parte === '') {
            continue;
        }
        if (str_contains($parte, ':')) {
            [$rotulo, $valor] = explode(':', $parte, 2);
            $chips[] = ['rotulo' => trim($rotulo), 'valor' => trim($valor)];
        } else {
            $chips[] = ['rotulo' => 'Aviso', 'valor' => $parte];
        }
    }
    $colunas = collect($colunas ?? [])->values()->all();
    $linhas = collect($linhas ?? [])->values()->all();
    $periodoCabecalho = $periodo ?? null;
    if (is_string($periodoCabecalho) && $periodoCabecalho !== '' && ! str_contains(mb_strtolower($periodoCabecalho), 'período') && ! str_contains(mb_strtolower($periodoCabecalho), 'periodo')) {
        $periodoCabecalho = 'Período de '.$periodoCabecalho;
    }
@endphp
<x-impressao.documento kind="Relatório" :nome="$titulo" :periodo="$periodoCabecalho">
    <x-impressao.filtros :chips="$chips" />

    @if(!empty($kpis))
        <x-impressao.kpis :cards="$kpis" />
    @endif

    @if(!empty($secao))
        <div class="sec-title">{{ $secao }}</div>
    @endif

    <table class="grid">
        <thead>
            <tr>
                @foreach($colunas as $coluna)
                    <th class="{{ ($coluna['align'] ?? 'left') === 'right' ? 'r' : '' }}"
                        @if(!empty($coluna['width'])) style="width: {{ $coluna['width'] }};" @endif>
                        {{ $coluna['label'] }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($linhas as $linha)
                <tr>
                    @foreach($linha as $indice => $celula)
                        @php
                            $rotulo = (string) ($colunas[$indice]['label'] ?? '');
                            $direita = ($colunas[$indice]['align'] ?? 'left') === 'right';
                            $data = preg_match('/data|validade|abertura|vencimento/i', $rotulo) === 1;
                        @endphp
                        <td class="{{ trim(($direita ? 'r num' : '').($data ? ' num muted' : '')) }}">
                            <x-impressao.situacao :texto="$celula" />
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($colunas), 1) }}">Nenhum registro para os filtros informados.</td>
                </tr>
            @endforelse
            @if($linhas !== [])
                <tr class="total">
                    <td colspan="{{ max(count($colunas), 1) }}">Total de {{ count($linhas) }} {{ \Illuminate\Support\Str::plural('registro', count($linhas)) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if(!empty($participacao['linhas']))
        <div class="sec-title">{{ $participacao['titulo'] ?? 'Participação por forma' }}</div>
        <table class="grid">
            <thead>
                <tr>
                    <th>Forma de pagamento</th>
                    <th class="r">Vendas</th>
                    <th class="r">Ticket médio</th>
                    <th>Participação</th>
                    <th class="r">Valor</th>
                </tr>
            </thead>
            <tbody>
                @foreach($participacao['linhas'] as $forma)
                    <tr>
                        <td>{{ $forma['nome'] }}</td>
                        <td class="r num">{{ $forma['vendas'] }}</td>
                        <td class="r num">{{ $forma['ticket'] }}</td>
                        <td>
                            <table>
                                <tr>
                                    <td style="width: 104px; padding: 0; border: none;">
                                        <div class="bar"><div class="bar-fill" style="width: {{ (int) $forma['percentual'] }}%;"></div></div>
                                    </td>
                                    <td class="bar-pct num" style="border: none; padding: 0;">{{ (int) $forma['percentual'] }}%</td>
                                </tr>
                            </table>
                        </td>
                        <td class="r num">{{ $forma['valor'] }}</td>
                    </tr>
                @endforeach
                @if(!empty($participacao['total']))
                    <tr class="total">
                        <td>Total</td>
                        <td class="r num">{{ $participacao['total']['vendas'] }}</td>
                        <td class="r num">{{ $participacao['total']['ticket'] }}</td>
                        <td class="num">100%</td>
                        <td class="r num">{{ $participacao['total']['valor'] }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @endif

    <x-impressao.resumo :linhas="$totais ?? []" />

    <x-impressao.assinaturas :linhas="$assinaturas ?? ['Responsável pela conferência', 'Data de conferência']" />
</x-impressao.documento>
