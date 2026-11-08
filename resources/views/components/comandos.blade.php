{{--
    Assistente: botão flutuante + painel (tela cheia no celular).
    Só aparece para quem tem a permissão "comandos"; cada tarefa aparece apenas se o perfil puder usá-la.
    A conversa é entendida no servidor (App\Comandos\Assistente), por regras, sem IA externa.
    Nada é gravado sem a confirmação. Comportamento em public/js/comandos.js; visual em public/css/comandos.css.
--}}
@php
    $usuario = auth()->user();
    $registroComandos = app(\App\Comandos\RegistroComandos::class);
    $rotinas = $usuario && $usuario->pode('comandos') ? $registroComandos->permitidos($usuario) : [];
    $exemplo = $rotinas !== [] ? $registroComandos->exemploPrincipal($usuario) : null;
@endphp

@if ($rotinas !== [])
    {{-- Botão e painel vão para o fim do body: fora do conteúdo, nada os esconde (rodapé, rolagem). --}}
    @push('modals')
        <button type="button" class="comandos-botao" data-comandos-abrir aria-controls="comandos-painel" aria-expanded="false"
                title="Assistente (Ctrl + K)">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/>
                <path d="M8.5 12h.01M12 12h.01M15.5 12h.01" stroke-width="2.6"/>
            </svg>
            <span class="comandos-botao__texto">Assistente</span>
        </button>

        <section id="comandos-painel" class="comandos" role="dialog" aria-modal="false" aria-labelledby="comandos-titulo" hidden
                 data-url-interpretar="{{ route('comandos.interpretar') }}"
                 data-url-confirmar="{{ route('comandos.confirmar') }}">
            <header class="comandos__topo">
                <span class="comandos__marca" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/>
                        <path d="M8.5 12h.01M12 12h.01M15.5 12h.01" stroke-width="2.6"/>
                    </svg>
                </span>
                <div class="comandos__cabecalho">
                    <h2 id="comandos-titulo" class="comandos__titulo">Assistente</h2>
                    <p class="comandos__subtitulo">Nada é salvo sem você confirmar.</p>
                </div>
                <button type="button" class="comandos__icone-btn" data-comandos-enviar="ajuda" data-comandos-rotulo="O que você faz?"
                        aria-label="Ver o que o assistente faz">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.3 2.4c-.5.2-.8.6-.8 1.1v.5M12 16.8v.2"/></svg>
                </button>
                <button type="button" class="comandos__icone-btn" data-comandos-fechar aria-label="Fechar o assistente">
                    <x-icon name="x" :size="18" />
                </button>
            </header>

            <div class="comandos__conversa" data-comandos-conversa role="log" aria-live="polite" aria-relevant="additions">
                <div class="comandos-boas-vindas">
                    <x-ilustracao nome="conversa" tamanho="sm" interativa />
                    <p class="comandos-boas-vindas__titulo">Oi, {{ \Illuminate\Support\Str::of($usuario->name)->before(' ') }}! Como posso ajudar?</p>
                    <p class="comandos-boas-vindas__texto">Escreva do seu jeito ou toque numa tarefa. Se faltar algo, eu pergunto.</p>
                    <div class="comandos-opcoes comandos-opcoes--centro">
                        @foreach ($rotinas as $rotina)
                            <button type="button" class="comandos-chip" data-comandos-enviar="{{ $rotina->atalho() }}"
                                    data-comandos-rotulo="{{ $rotina->titulo() }}">{{ $rotina->titulo() }}</button>
                        @endforeach
                    </div>
                    @if ($exemplo)
                        <button type="button" class="comandos-exemplo" data-comandos-exemplo="{{ $exemplo }}">
                            Ex.: “{{ $exemplo }}”
                        </button>
                    @endif
                </div>
            </div>

            <form class="comandos__envio" data-comandos-form novalidate>
                <label for="comandos-texto" class="sr-only">Escreva para o assistente</label>
                <input type="text" id="comandos-texto" name="texto" class="comandos__campo" maxlength="300"
                       autocomplete="off" autocapitalize="sentences" spellcheck="false" enterkeyhint="send"
                       placeholder="Escreva aqui..." data-placeholder-padrao="Escreva aqui...">
                <button type="submit" class="comandos__enviar" aria-label="Enviar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </button>
            </form>

            {{-- Visto animado clonado a cada tarefa concluída. --}}
            <template id="comandos-modelo-sucesso">
                <x-ilustracao nome="sucesso" tamanho="xs" class="comandos-msg__visto" />
            </template>
        </section>
    @endpush

    @once
        @push('styles')
            <link rel="stylesheet" href="{{ asset('css/comandos.css') }}?v={{ filemtime(public_path('css/comandos.css')) }}">
        @endpush
        @push('scripts')
            <script src="{{ asset('js/comandos.js') }}?v={{ filemtime(public_path('js/comandos.js')) }}"></script>
        @endpush
    @endonce
@endif
