@extends('layouts.app')

@section('title', 'Editar perfil fiscal')
@section('page_title', 'Editar perfil fiscal')

@section('breadcrumb')
    <a href="{{ route('perfilFiscal.index') }}">Perfis fiscais</a> / Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">{{ $perfil->nome }}</span>
        </div>
        <form method="POST" action="{{ route('perfilFiscal.update', $perfil) }}">
            @csrf
            @method('PUT')
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
