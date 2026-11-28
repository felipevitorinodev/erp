@extends('layouts.app')

@section('title', 'NFC-e emitidas')
@section('page_title', 'NFC-e emitidas')

@section('page_actions')
    <a href="{{ route('nfce.create') }}" class="btn btn--primary btn--sm">+ Nova NFC-e</a>
@endsection

@section('content')

    <div class="alert alert--info">
        A NFC-e sai do PDV, da tela da venda (quadro <strong>Nota fiscal</strong>) ou daqui, em <strong>+ Nova NFC-e</strong>.
        Consulta da pendente e cancelamento ficam na tela da venda.
    </div>

    <x-tabela-card titulo="Cupons fiscais" :paginator="$nfces">
        <x-slot:abas>
            <x-filtro-abas :opcoes="['autorizada' => 'Autorizadas', 'pendente' => 'Pendentes', 'rejeitada' => 'Rejeitadas', 'cancelada' => 'Canceladas']" />
        </x-slot:abas>
                    <thead>
                        <tr>
                            <th style="width:110px;">NFC-e</th>
                            <th style="width:90px;">Série</th>
                            <th>Venda</th>
                            <th>Consumidor</th>
                            <th>Emissão</th>
                            <th class="col-num">Valor</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($nfces as $nfce)
                            @php [$rotulo, $tom] = \App\Support\Situacoes::de('nfe', $nfce->situacao); @endphp
                            <tr>
                                <td data-label="NFC-e">#{{ ltrim((string) $nfce->numero, '0') ?: $nfce->numero }}</td>
                                <td data-label="Série" class="text-muted">{{ $nfce->serie }}</td>
                                <td data-label="Venda">
                                    @if ($nfce->venda)
                                        <a href="{{ route('venda.show', $nfce->venda) }}#nota-fiscal">#{{ $nfce->venda->numero }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Consumidor">{{ $nfce->venda?->cliente?->nome ?? 'Consumidor final' }}</td>
                                <td data-label="Emissão">{{ ($nfce->data_autorizacao ?? $nfce->created_at)?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td data-label="Valor" class="col-num">
                                    {{ $nfce->venda ? 'R$ '.number_format((float) $nfce->venda->total, 2, ',', '.') : '—' }}
                                </td>
                                <td data-label="Situação">
                                    <x-badge :tom="$tom">{{ $rotulo }}</x-badge>
                                    @if ((int) $nfce->ambiente !== 1)
                                        <x-badge tom="warning" title="Sem valor fiscal">Homologação</x-badge>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:extra>
                                            @if ($nfce->situacao === 'autorizada' && $nfce->venda)
                                                <a href="{{ route('venda.nfce.danfe', $nfce->venda) }}" target="_blank" class="btn btn--ghost btn--sm" title="DANFE" aria-label="DANFE">DANFE</a>
                                            @endif
                                            @if (in_array($nfce->situacao, ['autorizada', 'cancelada'], true))
                                                <a href="{{ route('nfce.xml', $nfce) }}" class="btn btn--ghost btn--sm" title="Baixar XML" aria-label="Baixar XML">XML</a>
                                            @endif
                                        </x-slot:extra>
                                        @if ($nfce->venda)
                                            <x-slot:ver>
                                                <a href="{{ route('venda.show', $nfce->venda) }}#nota-fiscal" class="btn btn--ghost btn--sm btn--icon" title="Ver na venda" aria-label="Ver na venda">
                                                    <x-icon name="eye" />
                                                </a>
                                            </x-slot:ver>
                                        @endif
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="documento-fiscal"
                                :colspan="8"
                                titulo="Nenhuma NFC-e emitida"
                                texto="Emita pelo PDV, pela venda confirmada ou em + Nova NFC-e."
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
