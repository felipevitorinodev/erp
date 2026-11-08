@extends('layouts.app')

@section('title', 'Nova conta a receber')
@section('page_title', 'Nova conta a receber')

@section('breadcrumb')
    <a href="{{ route('conta-receber.index') }}">Contas a receber</a> / Nova
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da conta</span>
        </div>

        <form method="POST" action="{{ route('conta-receber.store') }}">
            @csrf
            <div class="card__body">
                @include('conta-receber._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('conta-receber.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
