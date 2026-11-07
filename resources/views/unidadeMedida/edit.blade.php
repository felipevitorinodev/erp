@extends('layouts.app')

@section('title', 'Editar Unidade de Medida')
@section('page_title', 'Editar Unidade de Medida')

@section('breadcrumb')
    <a href="{{ route('unidadeMedida.index') }}">Unidades de Medida</a> / Editar Unidade
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Unidade</span>
        </div>

        <form method="POST" action="{{ route('unidadeMedida.update', $unidadeMedida) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
            <div class="card__body">
                <div class="form-grid form-grid--col-3">
                    <div class="form-group form-group--span-1">
                        <label class="form-label form-label--required">Nome</label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                            value="{{ old('nome', $unidadeMedida->nome) }}" maxlength="255">
                        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group form-group--span-1">
                        <label class="form-label form-label--required">Sigla</label>
                        <input type="text" name="sigla" class="form-control @error('sigla') is-invalid @enderror"
                            value="{{ old('sigla', $unidadeMedida->sigla) }}" maxlength="10">
                        @error('sigla')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group form-group--span-1">
                        <label class="form-label">Ativo</label>
                        <select name="ativo" class="form-control @error('ativo') is-invalid @enderror">
                            <option value="1" {{ old('ativo', $unidadeMedida->ativo) == '1' ? 'selected' : '' }}>Sim</option>
                            <option value="0" {{ old('ativo', $unidadeMedida->ativo) == '0' ? 'selected' : '' }}>Não</option>
                        </select>
                        @error('ativo')<span class="form-error">{{ $message }}</span>@enderror
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