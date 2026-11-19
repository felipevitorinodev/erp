@extends('layouts.app')

@section('title', 'Editar pedido ' . $pedido->numero)
@section('page_title', 'Editar pedido ' . $pedido->numero)

@section('breadcrumb')
    <a href="{{ route('pedido-compra.index') }}">Pedidos de compra</a> /
    <a href="{{ route('pedido-compra.show', $pedido) }}">{{ $pedido->numero }}</a> / Editar
@endsection

@section('content')
    <div class="card">
        <div class="card__header"><span class="card__title">Dados do pedido</span></div>
        <form method="POST" action="{{ route('pedido-compra.update', $pedido) }}">
            @csrf
            @method('PUT')
            <div class="card__body">@include('pedido-compra._form')</div>
            <div class="card__footer">
                <a href="{{ route('pedido-compra.show', $pedido) }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar alterações</button>
            </div>
        </form>
    </div>
@endsection
