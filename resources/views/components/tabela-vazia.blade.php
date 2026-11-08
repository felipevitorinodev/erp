{{--
    Linha de tabela sem registros. Com "titulo", mostra o estado vazio completo (x-estado-vazio);
    sem ele, o texto do slot vira o título de um estado compacto (relatórios e usos antigos).
--}}
@props([
    'colspan' => 1,
    'titulo' => null,
    'texto' => null,
    'ilustracao' => null,
    'acao' => null,
    'acaoTexto' => null,
    'filtravel' => false,
])

<tr class="tabela-vazia">
    <td colspan="{{ $colspan }}" {{ $attributes->class('empty-state') }}>
        <x-estado-vazio
            :titulo="$titulo ?? trim((string) $slot)"
            :texto="$texto"
            :ilustracao="$ilustracao ?? ($titulo ? 'vazio' : 'sem-resultado')"
            :acao="$acao"
            :acao-texto="$acaoTexto"
            :filtravel="$filtravel"
            :tamanho="$titulo ? 'sm' : 'xs'"
        />
    </td>
</tr>
