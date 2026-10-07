@extends('layouts.app')

@section('title', 'NF-e Emitidas')
@section('page_title', 'NF-e Emitidas')

@section('page_actions')
    <a href="{{ route('nfe.create') }}" class="btn btn--primary btn--sm">+ Nova NF-e</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de NF-e</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th style="width:90px;">Número</th>
                            <th style="width:70px;">Série</th>
                            <th>Venda</th>
                            <th>Data</th>
                            <th>Situação</th>
                            <th>Ambiente</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($nfes as $nfe)
                            <tr>
                                <td data-label="Número" class="text-muted">#{{ $nfe->numero }}</td>
                                <td data-label="Série">{{ $nfe->serie }}</td>
                                <td data-label="Venda">
                                    @if ($nfe->venda)
                                        <a href="{{ route('venda.show', $nfe->venda) }}">#{{ $nfe->venda->numero }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Data">
                                    {{ ($nfe->data_autorizacao ?? $nfe->created_at)?->format('d/m/Y H:i') ?? '—' }}
                                </td>
                                <td data-label="Situação">
                                    @if ($nfe->situacao === 'autorizada')
                                        <span class="badge badge--success">Autorizada</span>
                                    @elseif ($nfe->situacao === 'rejeitada')
                                        <span class="badge badge--error">Rejeitada</span>
                                    @elseif ($nfe->situacao === 'cancelada')
                                        <span class="badge badge--neutral">Cancelada</span>
                                    @else
                                        <span class="badge badge--warning">Pendente</span>
                                    @endif
                                </td>
                                <td data-label="Ambiente">
                                    @if ((int) $nfe->ambiente === 1)
                                        <span class="badge badge--success">Produção</span>
                                    @else
                                        <span class="badge badge--warning">Homologação</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('nfe.show', $nfe) }}" class="btn btn--ghost btn--sm btn--icon"
                                        title="Ver" aria-label="Ver">
                                        <x-icon name="eye" />
                                    </a>
                                    @if ($nfe->situacao === 'autorizada')
                                        <a href="{{ route('nfe.danfe', $nfe) }}" target="_blank"
                                            class="btn btn--ghost btn--sm" title="DANFE" aria-label="DANFE">
                                            DANFE
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted empty-state">
                                    Nenhuma NF-e emitida.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $nfes->withQueryString()->links() }}
        </div>
    </div>

@endsection
