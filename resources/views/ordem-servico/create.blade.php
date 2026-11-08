@extends('layouts.app')

@section('title', 'Nova ordem de serviço')
@section('page_title', 'Nova ordem de serviço')

@section('breadcrumb')
    <a href="{{ route('ordem-servico.index') }}">Ordens de serviço</a> / Nova
@endsection

@section('content')

    <form method="POST" action="{{ route('ordem-servico.store') }}" id="form-ordem-servico">
        @csrf
        @include('ordem-servico._form')

        <div class="form-actions" style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1rem;">
            <a href="{{ route('ordem-servico.index') }}" class="btn btn--ghost">Cancelar</a>
            <button type="submit" class="btn btn--primary">Abrir OS</button>
        </div>
    </form>

@endsection
