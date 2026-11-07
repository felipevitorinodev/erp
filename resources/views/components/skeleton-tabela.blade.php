@props(['linhas' => 8, 'colunas' => 5])

<div {{ $attributes->class('sk-tabela') }} role="status" aria-busy="true" aria-label="Carregando listagem">
    <div class="sk-tabela__barra">
        <span class="sk-bloco sk-bloco--titulo"></span>
        <span class="sk-bloco sk-bloco--botao"></span>
    </div>
    <div class="sk-tabela__grade">
        @for ($linha = 0; $linha < $linhas; $linha++)
            <div class="sk-tabela__linha">
                @for ($coluna = 0; $coluna < $colunas; $coluna++)
                    <span class="sk-bloco" style="width: {{ [42, 72, 58, 64, 36][$coluna % 5] }}%"></span>
                @endfor
            </div>
        @endfor
    </div>
</div>
