@extends('layouts.app')

@section('title', 'NFS-e emitidas')
@section('page_title', 'NFS-e emitidas')

@if (auth()->user()->pode('vendas'))
    @section('page_actions')
        <a href="{{ route('nfse.nova') }}" class="btn btn--primary btn--sm">+ Nova NFS-e</a>
    @endsection
@endif

@section('content')

    <div class="alert alert--info">
        A NFS-e Nacional sai da venda confirmada (<strong>Emitir NFS-e</strong> na tela da venda) ou daqui, em <strong>+ Nova NFS-e</strong>.
        Só os itens cadastrados como <strong>Serviço</strong> entram nela; os produtos da mesma venda vão na NF-e.
    </div>

    <x-tabela-card titulo="Notas de serviço" :paginator="$nfses">
        <x-slot:abas>
            <x-filtro-abas :opcoes="['autorizada' => 'Autorizadas', 'pendente' => 'Pendentes', 'rejeitada' => 'Rejeitadas', 'cancelada' => 'Canceladas']" />
        </x-slot:abas>
                    <thead>
                        <tr>
                            <th style="width:110px;">NFS-e</th>
                            <th style="width:110px;">DPS</th>
                            <th>Venda</th>
                            <th>Tomador</th>
                            <th>Competência</th>
                            <th class="col-num">Valor líquido</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($nfses as $nfse)
                            @php [$rotulo, $tom] = \App\Support\Situacoes::de('nfse', $nfse->situacao); @endphp
                            <tr>
                                <td data-label="NFS-e">{{ $nfse->numero_nfse ? '#'.$nfse->numero_nfse : '—' }}</td>
                                <td data-label="DPS" class="text-muted">{{ $nfse->serie }}/{{ $nfse->numero }}</td>
                                <td data-label="Venda">
                                    @if ($nfse->venda)
                                        <a href="{{ route('venda.show', $nfse->venda) }}">#{{ $nfse->venda->numero }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Tomador">{{ $nfse->venda?->cliente?->nome ?? 'Não identificado' }}</td>
                                <td data-label="Competência">{{ $nfse->data_competencia?->format('m/Y') ?? '—' }}</td>
                                <td data-label="Valor líquido" class="col-num">R$ {{ number_format((float) $nfse->valor_liquido, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    <x-badge :tom="$tom">{{ $rotulo }}</x-badge>
                                    @if ((int) $nfse->ambiente !== 1)
                                        <x-badge tom="warning" title="Produção restrita, sem valor fiscal">Homologação</x-badge>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:extra>
                                            @if (in_array($nfse->situacao, ['autorizada', 'cancelada'], true))
                                                <a href="{{ route('nfse.danfse', $nfse) }}" target="_blank" class="btn btn--ghost btn--sm" title="DANFSe" aria-label="DANFSe">DANFSe</a>
                                            @endif
                                        </x-slot:extra>
                                        <x-slot:ver>
                                            <a href="{{ route('nfse.show', $nfse) }}" class="btn btn--ghost btn--sm btn--icon" title="Ver" aria-label="Ver">
                                                <x-icon name="eye" />
                                            </a>
                                        </x-slot:ver>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="documento-fiscal"
                                :colspan="8"
                                titulo="Nenhuma NFS-e emitida"
                                texto="Emita pela venda confirmada que tem serviços ou em + Nova NFS-e."
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
