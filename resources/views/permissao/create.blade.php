@extends('layouts.app')

@section('title', 'Novo Perfil')
@section('page_title', 'Novo Perfil')

@section('breadcrumb')
    <a href="{{ route('permissao.index') }}">Permissões</a> / Novo
@endsection

@section('content')

    <div class="card">
        <form method="POST" action="{{ route('permissao.store') }}">
            @csrf
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
