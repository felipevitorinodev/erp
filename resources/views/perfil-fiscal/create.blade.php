@extends('layouts.app')

@section('title', 'Novo perfil fiscal')
@section('page_title', 'Novo perfil fiscal')

@section('breadcrumb')
    <a href="{{ route('perfilFiscal.index') }}">Perfis fiscais</a> / Novo
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados do perfil</span>
        </div>
        <form method="POST" action="{{ route('perfilFiscal.store') }}">
            @csrf
            <div class="card__body">
                @include('perfil-fiscal._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('perfilFiscal.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
