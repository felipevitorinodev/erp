@extends('layouts.app')

@section('title', 'Novo usuário')
@section('page_title', 'Novo usuário')

@section('breadcrumb')
    <a href="{{ route('usuario.index') }}">Usuários</a> / Novo
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados do usuário</span>
        </div>

        <form method="POST" action="{{ route('usuario.store') }}">
            @csrf
            <div class="card__body">
                @include('usuario._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('usuario.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
