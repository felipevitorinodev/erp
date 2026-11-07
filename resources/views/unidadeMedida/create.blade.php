@extends('layouts.app')

@section('title', 'Nova Unidade de Medida')
@section('page_title', 'Nova Unidade de Medida')

@section('breadcrumb')
    <a href="{{ route('unidadeMedida.index') }}">Unidades de Medida</a> / Nova Unidade
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Unidade</span>
        </div>

        <form method="POST" action="{{ route('unidadeMedida.store') }}">
            @csrf
            <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
            <div class="card__body">
                <div class="form-grid form-grid--col-2">
                    <div class="form-group">
                        <label class="form-label form-label--required">Nome</label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                            value="{{ old('nome') }}" maxlength="255" placeholder="Ex: Quilograma">
                        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label--required">Sigla</label>
                        <input type="text" name="sigla" class="form-control @error('sigla') is-invalid @enderror"
                            value="{{ old('sigla') }}" maxlength="10" placeholder="Ex: KG">
                        @error('sigla')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div class="card__footer">
                <a href="{{ route('unidadeMedida.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection