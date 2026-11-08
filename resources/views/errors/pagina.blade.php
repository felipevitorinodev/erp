{{--
    Tela única de erro HTTP. Os textos vêm de App\Support\PaginaErro.
    Não usa sessão, usuário nem banco: precisa abrir mesmo com o sistema fora do ar.
--}}
@php
    $pagina = \App\Support\PaginaErro::para($exception ?? null, $padrao ?? 500);
    $inicio = url('/');
    $entrar = \Illuminate\Support\Facades\Route::has('login') ? route('login') : url('/login');
    $versao = fn (string $arquivo) => asset($arquivo).'?v='.@filemtime(public_path($arquivo));
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $pagina['titulo'] }} — Visys</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ $versao('css/app.css') }}">
    <link rel="stylesheet" href="{{ $versao('css/ilustracoes.css') }}">
</head>
<body class="erro-body" data-ilustracao-area>
    <main class="erro">
        <a href="{{ $inicio }}" class="erro__marca">
            <img src="{{ asset('img/logo-v-sem-fundo.png') }}" alt="" width="28" height="28">
            <span>Visys</span>
        </a>

        <x-ilustracao :nome="$pagina['ilustracao']" tamanho="lg" interativa class="erro__arte" />

        <p class="erro__codigo">Erro {{ $pagina['codigo'] }}</p>
        <h1 class="erro__titulo">{{ $pagina['titulo'] }}</h1>
        <p class="erro__texto">{{ $pagina['texto'] }}</p>

        @if ($pagina['espera'])
            @if ($pagina['recarregar'])
                <x-contagem :segundos="$pagina['espera']" acao="recarregar" class="erro__contagem">
                    Nova tentativa automática em instantes.
                </x-contagem>
            @else
                <x-contagem :segundos="$pagina['espera']" botao="#erro-tentar" class="erro__contagem">
                    Aguarde para tentar de novo.
                </x-contagem>
            @endif
        @endif

        @if ($pagina['horario'])
            <p class="erro__horario">Horário do erro: {{ now()->format('d/m/Y H:i:s') }}</p>
        @endif

        <div class="erro__acoes">
            @foreach ($pagina['acoes'] as $indice => $acao)
                @php($classe = $indice === 0 ? 'btn btn--primary' : 'btn btn--ghost')
                @switch($acao)
                    @case('voltar')
                        <button type="button" class="{{ $classe }}" data-erro-voltar data-inicio="{{ $inicio }}">Voltar</button>
                        @break
                    @case('tentar')
                        <button type="button" class="{{ $classe }}" id="erro-tentar" data-erro-voltar data-inicio="{{ $inicio }}" disabled>Tentar novamente</button>
                        @break
                    @case('recarregar')
                        <button type="button" class="{{ $classe }}" data-erro-recarregar>Tentar novamente</button>
                        @break
                    @case('entrar')
                        <a href="{{ $entrar }}" class="{{ $classe }}">Entrar</a>
                        @break
                    @case('inicio')
                        <a href="{{ $inicio }}" class="{{ $classe }}">Ir para o início</a>
                        @break
                @endswitch
            @endforeach
        </div>
    </main>

    <script src="{{ $versao('js/ilustracoes.js') }}"></script>
</body>
</html>
