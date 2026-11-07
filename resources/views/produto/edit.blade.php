@extends('layouts.app')

@section('title', 'Editar Produto')
@section('page_title', 'Editar Produto')

@section('breadcrumb')
    <a href="{{ route('produto.index') }}">Produtos</a> / Editar Produto
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados do Produto</span>
        </div>

        <form method="POST" action="{{ route('produto.update', $produto) }}">
            @csrf
            @method('PUT')
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