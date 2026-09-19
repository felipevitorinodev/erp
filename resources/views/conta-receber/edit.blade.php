@extends('layouts.app')

@section('title', 'Editar Conta a Receber')
@section('page_title', 'Editar Conta a Receber')

@section('breadcrumb')
    <a href="{{ route('conta-receber.index') }}">Contas a Receber</a> /
    <a href="{{ route('conta-receber.show', $conta) }}">#{{ $conta->id }}</a> /
    Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Conta</span>
        </div>

        <form method="POST" action="{{ route('conta-receber.update', $conta) }}">
            @csrf
            @method('PUT')
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
