@extends('layouts.app')

@section('title', 'Nova Categoria Financeira')
@section('page_title', 'Nova Categoria Financeira')

@section('breadcrumb')
    <a href="{{ route('categoria-financeira.index') }}">Categorias Financeiras</a> / Nova
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Categoria</span>
        </div>

        <form method="POST" action="{{ route('categoria-financeira.store') }}">
            @csrf
            <div class="card__body">
                @include('categoria-financeira._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('categoria-financeira.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
