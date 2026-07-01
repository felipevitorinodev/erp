@extends('layouts.app')

@section('title', 'Editar Funcionário')
@section('page_title', 'Editar Funcionário')

@section('breadcrumb')
    <a href="{{ route('funcionario.index') }}">Funcionários</a> / Editar Funcionário
@endsection

@section('content')

<div class="card">
    <div class="card__header">
        <span class="card__title">Dados do Funcionário</span>
    </div>

    <form method="POST" action="{{ route('funcionario.update', $funcionario) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
        <div class="card__body">

            <div class="form-grid form-grid--col-2">
                <div class="form-group">
                    <label class="form-label form-label--required">Nome</label>
                    <input type="text" name="nome"
                        class="form-control @error('nome') is-invalid @enderror"
                        value="{{ old('nome', $funcionario->nome) }}" maxlength="255">
                    @error('nome')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Usuário do sistema</label>
                    <input type="text" class="form-control" value="{{ optional($funcionario->usuario)->name }}" readonly>
                    <input type="hidden" name="usuario_id" value="{{ old('usuario_id', $funcionario->usuario_id) }}">
                </div>
            </div>

            <div class="form-grid form-grid--col-3" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">CPF</label>
                    <input type="text" name="cpf"
                        class="form-control @error('cpf') is-invalid @enderror"
                        value="{{ old('cpf', $funcionario->cpf) }}" maxlength="14" placeholder="000.000.000-00">
                    @error('cpf')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">RG</label>
                    <input type="text" name="rg"
                        class="form-control @error('rg') is-invalid @enderror"
                        value="{{ old('rg', $funcionario->rg) }}" maxlength="20">
                    @error('rg')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Data de Nascimento</label>
                    <input type="date" name="data_nascimento"
                        class="form-control @error('data_nascimento') is-invalid @enderror"
                        value="{{ old('data_nascimento', optional($funcionario->data_nascimento)->format('Y-m-d')) }}">
                    @error('data_nascimento')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--col-3" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone"
                        class="form-control @error('telefone') is-invalid @enderror"
                        value="{{ old('telefone', $funcionario->telefone) }}" maxlength="20" placeholder="(00) 0000-0000">
                    @error('telefone')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Celular</label>
                    <input type="text" name="celular"
                        class="form-control @error('celular') is-invalid @enderror"
                        value="{{ old('celular', $funcionario->celular) }}" maxlength="20" placeholder="(00) 00000-0000">
                    @error('celular')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email', $funcionario->email) }}" maxlength="255">
                    @error('email')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--col-4" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Cargo</label>
                    <input type="text" name="cargo"
                        class="form-control @error('cargo') is-invalid @enderror"
                        value="{{ old('cargo', $funcionario->cargo) }}" maxlength="100">
                    @error('cargo')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Departamento</label>
                    <input type="text" name="departamento"
                        class="form-control @error('departamento') is-invalid @enderror"
                        value="{{ old('departamento', $funcionario->departamento) }}" maxlength="100">
                    @error('departamento')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Salário</label>
                    <input type="number" step="0.01" name="salario"
                        class="form-control @error('salario') is-invalid @enderror"
                        value="{{ old('salario', $funcionario->salario) }}">
                    @error('salario')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Tipo de Contrato</label>
                    <select name="tipo_contrato" class="form-control @error('tipo_contrato') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach(['CLT', 'PJ', 'Autonomo', 'Estagio'] as $tipo)
                            <option value="{{ $tipo }}" {{ old('tipo_contrato', $funcionario->tipo_contrato) == $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                        @endforeach
                    </select>
                    @error('tipo_contrato')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--col-3" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Data de Admissão</label>
                    <input type="date" name="data_admissao"
                        class="form-control @error('data_admissao') is-invalid @enderror"
                        value="{{ old('data_admissao', optional($funcionario->data_admissao)->format('Y-m-d')) }}">
                    @error('data_admissao')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Data de Demissão</label>
                    <input type="date" name="data_demissao"
                        class="form-control @error('data_demissao') is-invalid @enderror"
                        value="{{ old('data_demissao', optional($funcionario->data_demissao)->format('Y-m-d')) }}">
                    @error('data_demissao')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Ativo</label>
                    <select name="ativo" class="form-control @error('ativo') is-invalid @enderror">
                        <option value="1" {{ old('ativo', $funcionario->ativo) == '1' ? 'selected' : '' }}>Sim</option>
                        <option value="0" {{ old('ativo', $funcionario->ativo) == '0' ? 'selected' : '' }}>Não</option>
                    </select>
                    @error('ativo')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Observações</label>
                    <textarea style="resize:none;" name="observacoes"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        rows="3" maxlength="1000">{{ old('observacoes', $funcionario->observacoes) }}</textarea>
                    @error('observacoes')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

        </div>

        <div class="card__footer">
            <a href="{{ route('funcionario.index') }}" class="btn btn--ghost">Cancelar</a>
            <button type="submit" class="btn btn--primary">Salvar</button>
        </div>

    </form>
</div>

@endsection