@extends('layouts.app')

@section('title', 'Nova conta a pagar')
@section('page_title', 'Nova conta a pagar')

@section('breadcrumb')
    <a href="{{ route('conta-pagar.index') }}">Contas a pagar</a> / Nova
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da conta</span>
        </div>

        <form method="POST" action="{{ route('conta-pagar.store') }}">
            @csrf
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
