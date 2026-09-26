@extends('layouts.app')

@section('title', 'Nova Entrada de Estoque')
@section('page_title', 'Nova Entrada de Estoque')

@section('breadcrumb')
    <a href="{{ route('entrada-estoque.index') }}">Entradas de Estoque</a> / Nova
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Entrada</span>
        </div>

        <form method="POST" action="{{ route('entrada-estoque.store') }}">
            @csrf
            <div class="card__body">
                @include('entrada-estoque._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('entrada-estoque.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
