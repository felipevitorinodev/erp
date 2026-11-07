<div {{ $attributes->class('sk-formulario') }} role="status" aria-busy="true" aria-label="Carregando formulário">
    <span class="sk-bloco sk-bloco--titulo"></span>
    <div class="sk-formulario__grade">
        @for ($i = 0; $i < 6; $i++)
            <div class="sk-campo">
                <span class="sk-bloco sk-bloco--rotulo"></span>
                <span class="sk-bloco sk-bloco--controle"></span>
            </div>
        @endfor
    </div>
    <span class="sk-bloco sk-bloco--botao"></span>
</div>
