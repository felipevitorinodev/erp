@extends('layouts.app')

@section('title', 'Nova Conta a Pagar')
@section('page_title', 'Nova Conta a Pagar')

@section('breadcrumb')
    <a href="{{ route('conta-pagar.index') }}">Contas a Pagar</a> / Nova
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Conta</span>
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
