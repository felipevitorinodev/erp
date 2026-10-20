@extends('layouts.app')

@section('title', 'Editar OS #' . $os->numero)
@section('page_title', 'Editar OS #' . $os->numero)

@section('breadcrumb')
    <a href="{{ route('ordem-servico.index') }}">Ordens de Serviço</a> /
    <a href="{{ route('ordem-servico.show', $os) }}">#{{ $os->numero }}</a> /
    Editar
@endsection

@section('content')

    <form method="POST" action="{{ route('ordem-servico.update', $os) }}" id="form-ordem-servico">
        @csrf
        @method('PUT')
        @include('ordem-servico._form')

        <div class="form-actions" style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
            <a href="{{ route('ordem-servico.show', $os) }}" class="btn btn--ghost">Cancelar</a>
            <button type="submit" class="btn btn--primary">Salvar</button>
        </div>
    </form>

@endsection
