<div {{ $attributes->class('sk-ficha') }} role="status" aria-busy="true" aria-label="Carregando registro">
    <span class="sk-bloco sk-bloco--titulo"></span>
    <div class="sk-ficha__grade">
        @for ($i = 0; $i < 6; $i++)
            <div class="sk-campo">
                <span class="sk-bloco sk-bloco--rotulo"></span>
                <span class="sk-bloco sk-bloco--controle"></span>
            </div>
        @endfor
    </div>
</div>
