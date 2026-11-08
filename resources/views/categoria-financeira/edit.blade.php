@extends('layouts.app')

@section('title', 'Editar categoria financeira')
@section('page_title', 'Editar categoria financeira')

@section('breadcrumb')
    <a href="{{ route('categoria-financeira.index') }}">Categorias financeiras</a> / Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da categoria</span>
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
