@extends('layouts.app')

@section('title', 'Editar Venda')
@section('page_title', 'Editar Venda')

@section('breadcrumb')
    <a href="{{ route('venda.index') }}">Vendas</a> /
    <a href="{{ route('venda.show', $venda) }}">#{{ $venda->numero }}</a> /
    Editar
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pagamento-modal.css') }}?v={{ filemtime(public_path('css/pagamento-modal.css')) }}">
@endpush

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Venda</span>
        </div>

        <form method="POST" action="{{ route('venda.update', $venda) }}" id="form-venda">
            @csrf
            @method('PUT')
            <div class="card__body">
                @include('venda._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('venda.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
        @include('venda._pagamento-modal')
    </div>

@endsection

@push('scripts')
<script src="{{ asset('js/pagamento-modal.js') }}?v={{ filemtime(public_path('js/pagamento-modal.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.PagamentoModal) return;
    var pagamento = window.PagamentoModal.iniciarVenda({
        root: 'venda-pagamento',
        form: 'form-venda',
        abrir: 'btn-escolher-pagamento',
        hidden: 'hidden-forma-pagamento',
        resumo: 'pagamento-resumo',
        campos: 'venda-parcelas',
        enviar: false,
        total: function () {
            return parseFloat(document.getElementById('input-total').value) || 0;
        },
        dataBase: function () {
            var campo = document.querySelector('#form-venda [name="data_venda"]');
            return campo && campo.value ? campo.value : new Date().toISOString().slice(0, 10);
        },
        subtitulo: function () {
            var cliente = document.getElementById('input-cliente');
            var nome = cliente && cliente.value ? cliente.value : 'Consumidor final';
            return 'Venda Nº {{ $venda->numero }} · ' + nome;
        }
    });
});
</script>
@endpush