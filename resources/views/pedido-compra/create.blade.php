@extends('layouts.app')

@section('title', 'Novo pedido de compra')
@section('page_title', 'Novo pedido de compra')

@section('breadcrumb')
    <a href="{{ route('pedido-compra.index') }}">Pedidos de compra</a> / Novo
@endsection

@section('content')
    <div class="card">
        <div class="card__header"><span class="card__title">Dados do pedido</span></div>
        <form method="POST" action="{{ route('pedido-compra.store') }}">
            @csrf
            <div class="card__body">@include('pedido-compra._form')</div>
            <div class="card__footer">
                <a href="{{ route('pedido-compra.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar rascunho</button>
            </div>
        </form>
    </div>
@endsection
