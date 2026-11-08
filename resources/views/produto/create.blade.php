@extends('layouts.app')

@section('title', 'Novo produto')
@section('page_title', 'Novo produto')

@section('breadcrumb')
    <a href="{{ route('produto.index') }}">Produtos</a> / Novo produto
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados do produto</span>
        </div>

        <form method="POST" action="{{ route('produto.store') }}">
            @csrf
            <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
            <div class="card__body">
                @include('produto._form')
            </div>

            <div class="card__footer">
                <a href="{{ route('produto.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection