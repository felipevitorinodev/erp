@extends('layouts.app')

@section('title', 'Nova Venda')
@section('page_title', 'Nova Venda')

@section('breadcrumb')
    <a href="{{ route('venda.index') }}">Vendas</a> / Nova Venda
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Venda</span>
        </div>

        <form method="POST" action="{{ route('venda.store') }}">
            @csrf
            <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
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
