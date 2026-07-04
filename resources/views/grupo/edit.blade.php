@extends('layouts.app')

@section('title', 'Editar Grupo')
@section('page_title', 'Editar Grupo')

@section('breadcrumb')
    <a href="{{ route('grupo.index') }}">Grupos</a> / Editar Grupo
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Grupo</span>
        </div>

        <form method="POST" action="{{ route('grupo.update', $grupo) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
            <div class="card__body">

                <div class="form-grid form-grid--col-3">
                    <div class="form-group">
                        <label class="form-label form-label--required">Nome</label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                            value="{{ old('nome', $grupo->nome) }}" maxlength="255">
                        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Grupo Pai</label>
                        <select name="parent_id" class="form-control @error('parent_id') is-invalid @enderror">
                            <option value="">Nenhuma (Grupo principal)</option>
                            @foreach($gruposPrincipais as $cat)
                                @if($cat->id !== $grupo->id)
                                    <option value="{{ $cat->id }}" {{ old('parent_id', $grupo->parent_id) == $cat->id ? 'selected' : '' }}>{{ $cat->nome }}</option>
                                @endif
                            @endforeach
                        </select>
                        @error('parent_id')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">Ativo</label>
                            <select name="ativo" class="form-control @error('ativo') is-invalid @enderror">
                                <option value="1" {{ old('ativo', $grupo->ativo) == '1' ? 'selected' : '' }}>Sim</option>
                                <option value="0" {{ old('ativo', $grupo->ativo) == '0' ? 'selected' : '' }}>Não</option>
                            </select>
                            @error('ativo')<span class="form-error">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-grid" style="margin-top:1rem;">
                    <div class="form-group">
                        <label class="form-label">Descrição</label>
                        <textarea name="descricao" class="form-control @error('descricao') is-invalid @enderror"
                            rows="3">{{ old('descricao', $grupo->descricao) }}</textarea>
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