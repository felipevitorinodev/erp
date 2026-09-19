@extends('layouts.app')

@section('title', 'Editar Orçamento')
@section('page_title', 'Editar Orçamento')

@section('breadcrumb')
    <a href="{{ route('orcamento.index') }}">Orçamentos</a> /
    <a href="{{ route('orcamento.show', $orcamento) }}">#{{ $orcamento->numero }}</a> /
    Editar
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados do Orçamento</span>
        </div>

        <form method="POST" action="{{ route('orcamento.update', $orcamento) }}">
            @csrf
            @method('PUT')
            <div class="card__body">
                @include('orcamento._form')
            </div>
            <div class="card__footer">
                <a href="{{ route('orcamento.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection
