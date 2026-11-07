@extends('layouts.app')

@section('title', 'Editar Entrada #' . $entrada->numero)
@section('page_title', 'Editar Entrada #' . $entrada->numero)

@section('breadcrumb')
    <a href="{{ route('entrada-estoque.index') }}">Entradas de Estoque</a> /
    <a href="{{ route('entrada-estoque.show', $entrada) }}">#{{ $entrada->numero }}</a> /
    Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Entrada</span>
        </div>

        <form method="POST" action="{{ route('entrada-estoque.update', $entrada) }}">
            @csrf
            @method('PUT')
            <div class="card__body">
                @include('entrada-estoque._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('entrada-estoque.show', $entrada) }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
