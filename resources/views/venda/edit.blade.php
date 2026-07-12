@extends('layouts.app')

@section('title', 'Editar Venda')
@section('page_title', 'Editar Venda')

@section('breadcrumb')
    <a href="{{ route('venda.index') }}">Vendas</a> /
    <a href="{{ route('venda.show', $venda) }}">#{{ $venda->numero }}</a> /
    Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Venda</span>
        </div>

        <form method="POST" action="{{ route('venda.update', $venda) }}">
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
    </div>

@endsection