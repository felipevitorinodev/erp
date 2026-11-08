@extends('layouts.app')

@section('title', 'Perfis fiscais')
@section('page_title', 'Perfis fiscais')

@section('page_actions')
    <a href="{{ route('perfilFiscal.create') }}" class="btn btn--primary btn--sm">+ Novo perfil fiscal</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('perfilFiscal.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome, NCM ou CFOP">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('perfilFiscal.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Resultados</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Contexto</th>
                            <th>NCM</th>
                            <th>CFOP</th>
                            <th>CSOSN</th>
                            <th>PIS / COFINS</th>
                            <th>Produtos</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php($catalogoCfop = \App\Models\Fiscal\Cfop::query()->pluck('descricao', 'codigo'))
                        @forelse ($perfis as $perfil)
                            <tr>
                                <td data-label="Nome">{{ $perfil->nome }}</td>
                                <td data-label="Contexto">
                                    {{ $perfil->operacao === 'devolucao' ? 'Devolução' : ($perfil->operacao === 'venda' ? 'Venda' : 'Qualquer operação') }}
                                    · {{ $perfil->uf_destino ?: 'qualquer UF' }}
                                </td>
                                <td data-label="NCM">{{ $perfil->ncm ?: '—' }}</td>
                                <td data-label="CFOP">{{ $perfil->cfop }}{{ isset($catalogoCfop[$perfil->cfop]) ? ' - '.$catalogoCfop[$perfil->cfop] : '' }}</td>
                                <td data-label="CSOSN">{{ $perfil->csosn }}</td>
                                <td data-label="PIS / COFINS">{{ $perfil->cst_pis }} / {{ $perfil->cst_cofins }}</td>
                                <td data-label="Produtos">{{ $perfil->produtos_count }}</td>
                                <td data-label="Situação">
                                    @if ($perfil->ativo)
                                        <span class="badge badge--success">Ativo</span>
                                    @else
                                        <span class="badge badge--neutral">Inativo</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('perfilFiscal.edit', $perfil) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('perfilFiscal.destroy', $perfil) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir este perfil? Os produtos ligados a ele passam a usar o padrão da empresa."
                                                    data-confirm-title="Excluir perfil" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="documento-fiscal"
                                :colspan="9"
                                titulo="Nenhum perfil fiscal cadastrado"
                                texto="Enquanto não houver, a nota continua usando o produto e o padrão da empresa."
                                :acao="route('perfilFiscal.create')"
                                acao-texto="+ Novo perfil fiscal"
                                filtravel
                            />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $perfis->withQueryString()->links() }}
        </div>
    </div>

@endsection
