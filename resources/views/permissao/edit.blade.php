@extends('layouts.app')

@section('title', 'Editar Perfil')
@section('page_title', 'Editar ' . $perfil->nome)

@section('breadcrumb')
    <a href="{{ route('permissao.index') }}">Permissões</a> / {{ $perfil->nome }}
@endsection

@section('content')

    <div class="card">
        <form method="POST" action="{{ route('permissao.update', $perfil) }}">
            @csrf
            @method('PUT')
            <div class="card__body">
                @include('permissao._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('permissao.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
