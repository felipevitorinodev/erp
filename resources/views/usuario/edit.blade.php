@extends('layouts.app')

@section('title', 'Editar Usuário')
@section('page_title', 'Editar Usuário')

@section('breadcrumb')
    <a href="{{ route('usuario.index') }}">Usuários</a> / Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados do Usuário</span>
        </div>

        <form method="POST" action="{{ route('usuario.update', $usuario) }}">
            @csrf
            @method('PUT')
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
