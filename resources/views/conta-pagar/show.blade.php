@extends('layouts.app')

@section('title', 'Conta a Pagar #' . $conta->id)
@section('page_title', 'Conta a Pagar #' . $conta->id)

@section('breadcrumb')
    <a href="{{ route('conta-pagar.index') }}">Contas a Pagar</a> / #{{ $conta->id }}
@endsection

@section('page_actions')
    <div class="page-actions">

        <a href="{{ route('conta-pagar.index') }}" class="btn btn--ghost btn--sm">Voltar</a>
        
        @if (!in_array($conta->situacao, ['paga', 'cancelada'], true))
            <button type="button" class="btn btn--success btn--sm" id="btn-abrir-pagar">Registrar Pagamento</button>
            <a href="{{ route('conta-pagar.edit', $conta) }}" class="btn btn--ghost btn--sm">Editar</a>
            <form method="POST" action="{{ route('conta-pagar.cancelar', $conta) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn--danger btn--sm" data-confirm="Cancelar esta conta?"
                    data-confirm-title="Cancelar conta" data-confirm-ok="Cancelar conta" data-confirm-variant="danger">
                    Cancelar
                </button>
            </form>
        @endif

        <form method="POST" action="{{ route('conta-pagar.destroy', $conta) }}" class="d-inline">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn--danger btn--sm" data-confirm="Confirmar exclusão?"
                data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                Excluir
            </button>
        </form>
    </div>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Conta</span>
            <div>
                @if ($conta->situacao === 'paga')
                    <span class="badge badge--success">Paga</span>
                @elseif($conta->situacao === 'parcial')
                    <span class="badge badge--info">Parcial</span>
                @elseif($conta->situacao === 'cancelada')
                    <span class="badge badge--error">Cancelada</span>
                @else
                    <span class="badge badge--warning">Aberta</span>
                @endif
            </div>
        </div>
        <div class="card__body">
            <div class="form-grid form-grid--col-2">
                <div class="form-group">
                    <label class="form-label">Descrição</label>
                    <span>{{ $conta->descricao }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Fornecedor</label>
                    <span>{{ $conta->fornecedor->nome ?? '—' }}</span>
                </div>
            </div>
            <div class="form-grid form-grid--col-4" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Valor</label>
                    <span>R$ {{ number_format($conta->valor, 2, ',', '.') }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Valor Pago</label>
                    <span>R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Saldo</label>
                    <span>R$ {{ number_format($conta->valor - $conta->valor_pago, 2, ',', '.') }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Vencimento</label>
                    <span>{{ $conta->data_vencimento->format('d/m/Y') }}</span>
                </div>
            </div>
            <div class="form-grid form-grid--col-3" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Data Pagamento</label>
                    <span>{{ $conta->data_pagamento ? $conta->data_pagamento->format('d/m/Y') : '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Forma de Pagamento</label>
                    <span>{{ $conta->forma_pagamento ?? '—' }}</span>
                </div>
            </div>
            @if ($conta->observacoes)
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Observações</label>
                    <span>{{ $conta->observacoes }}</span>
                </div>
            @endif
        </div>
    </div>

    @if (!in_array($conta->situacao, ['paga', 'cancelada'], true))
        <div class="modal-overlay" id="modal-pagar" hidden>
            <div class="modal-box">
                <div class="modal-box__header">
                    <span class="card__title">Registrar Pagamento</span>
                    <button type="button" class="btn btn--ghost btn--sm" id="btn-fechar-pagar">✕</button>
                </div>
                <form method="POST" action="{{ route('conta-pagar.pagar', $conta) }}">
                    @csrf
                    <div class="modal-box__body">
                        <div class="form-group">
                            <label class="form-label form-label--required">Valor Pago</label>
                            @include('components.input-numeric', [
                                'name' => 'valor_pago',
                                'value' => old(
                                    'valor_pago',
                                    number_format($conta->valor - $conta->valor_pago, 2, ',', '.')),
                                'class' => 'input-moeda',
                                'decimals' => 2,
                                'required' => true,
                            ])
                            @error('valor_pago')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group" style="margin-top:0.75rem;">
                            <label class="form-label form-label--required">Data Pagamento</label>
                            <input type="date" name="data_pagamento" class="form-control"
                                value="{{ old('data_pagamento', date('Y-m-d')) }}" required>
                            @error('data_pagamento')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group" style="margin-top:0.75rem;">
                            <label class="form-label">Forma de Pagamento</label>
                            <input type="text" name="forma_pagamento" class="form-control"
                                value="{{ old('forma_pagamento', $conta->forma_pagamento) }}" maxlength="100">
                        </div>
                    </div>
                    <div class="modal-box__footer">
                        <button type="button" class="btn btn--ghost" id="btn-cancelar-pagar">Fechar</button>
                        <button type="submit" class="btn btn--primary">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script>
        (function() {
            var modal = document.getElementById('modal-pagar');
            if (!modal) return;

            function abrir() {
                modal.hidden = false;
            }

            function fechar() {
                modal.hidden = true;
            }

            var btnAbrir = document.getElementById('btn-abrir-pagar');
            if (btnAbrir) btnAbrir.addEventListener('click', abrir);

            ['btn-fechar-pagar', 'btn-cancelar-pagar'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', fechar);
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) fechar();
            });

            @if ($errors->has('valor_pago') || $errors->has('data_pagamento'))
                abrir();
            @endif
        })();
    </script>
@endpush
