@extends('layouts.app')

@section('title', 'Relatórios')
@section('page_title', 'Relatórios')

@section('content')

    <p class="relatorios-hub__intro">Escolha um relatório para consultar. A lista mostra apenas o que o seu perfil pode acessar.</p>

    <div class="form-group relatorios-busca">
        <label class="form-label" for="relatorios-busca">Buscar relatório</label>
        <input
            type="search"
            id="relatorios-busca"
            class="form-control"
            placeholder="Nome ou descrição"
            autocomplete="off"
            enterkeyhint="search"
        >
    </div>

    @foreach ($categorias as $categoria)
        <section class="relatorios-categoria" data-relatorios-categoria>
            <h2 class="relatorios-categoria__titulo">
                <span class="relatorios-categoria__icone" aria-hidden="true">
                    <x-icon :name="$categoria['icone']" :size="16" />
                </span>
                {{ $categoria['titulo'] }}
            </h2>
            <div class="relatorios-grid">
                @foreach ($categoria['relatorios'] as $relatorio)
                    <a
                        href="{{ route($relatorio['rota']) }}"
                        class="relatorios-card"
                        data-relatorio
                        data-busca="{{ \Illuminate\Support\Str::lower($relatorio['titulo'].' '.$relatorio['descricao']) }}"
                    >
                        <span class="relatorios-card__icone" aria-hidden="true">
                            <x-icon :name="$relatorio['icone']" :size="18" />
                        </span>
                        <span class="relatorios-card__titulo">{{ $relatorio['titulo'] }}</span>
                        <span class="relatorios-card__descricao">{{ $relatorio['descricao'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach

    <p class="relatorios-vazio relatorios-vazio--oculto" id="relatorios-vazio" role="status">
        Nenhum relatório encontrado para essa busca.
    </p>

@endsection

@push('scripts')
    <script src="{{ asset('js/relatorios-hub.js') }}?v={{ filemtime(public_path('js/relatorios-hub.js')) }}"></script>
@endpush
