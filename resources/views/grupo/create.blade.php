@extends('layouts.app')

@section('title', 'Nova grupo')
@section('page_title', 'Nova grupo')

@section('breadcrumb')
    <a href="{{ route('grupo.index') }}">grupos</a> / Nova grupo
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da grupo</span>
        </div>

        <form method="POST" action="{{ route('grupo.store') }}">
            @csrf
            <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
            <div class="card__body">

                <div class="form-grid form-grid--col-2">
                    <div class="form-group">
                        <label class="form-label form-label--required">Nome</label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                            value="{{ old('nome') }}" maxlength="255">
                        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">grupo Pai</label>
                        <select name="parent_id" class="form-control @error('parent_id') is-invalid @enderror">
                            <option value="">Nenhuma (grupo principal)</option>
                            @foreach($gruposPrincipais as $cat)
                                <option value="{{ $cat->id }}" {{ old('parent_id') == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->nome }}</option>
                            @endforeach
                        </select>
                        @error('parent_id')<span class="form-error">{{ $message }}</span>@enderror
                        <small class="text-muted">Selecione se esta for uma subgrupo.</small>
                    </div>
                </div>

                <div class="form-grid" style="margin-top:1rem;">
                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control @error('descricao') is-invalid @enderror"
                            rows="3">{{ old('descricao') }}</textarea>
                        @error('descricao')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

            </div>

            <div class="card__footer">
                <a href="{{ route('grupo.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>

        </form>
    </div>

@endsection