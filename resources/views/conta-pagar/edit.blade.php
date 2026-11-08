@extends('layouts.app')

@section('title', 'Editar conta a pagar')
@section('page_title', 'Editar conta a pagar')

@section('breadcrumb')
    <a href="{{ route('conta-pagar.index') }}">Contas a pagar</a> /
    <a href="{{ route('conta-pagar.show', $conta) }}">#{{ $conta->id }}</a> /
    Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da conta</span>
        </div>

        <form method="POST" action="{{ route('conta-pagar.update', $conta) }}">
            @csrf
            @method('PUT')
            <div class="card__body">
                @include('conta-pagar._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('conta-pagar.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
