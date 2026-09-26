@extends('layouts.app')

@section('title', 'Editar Categoria Financeira')
@section('page_title', 'Editar Categoria Financeira')

@section('breadcrumb')
    <a href="{{ route('categoria-financeira.index') }}">Categorias Financeiras</a> / Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Categoria</span>
        </div>

        <form method="POST" action="{{ route('categoria-financeira.update', $categoriaFinanceira) }}">
            @csrf
            @method('PUT')
            <div class="card__body">
                @include('categoria-financeira._form', ['categoriaFinanceira' => $categoriaFinanceira])
            </div>
            <div class="card__footer">
                <a href="{{ route('categoria-financeira.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
