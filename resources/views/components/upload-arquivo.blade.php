{{--
    Área de envio de arquivo: clique ou arraste. Mostra o nome escolhido com botão de remover
    e recusa, no navegador, extensão fora de "accept". Comportamento em public/js/app.js
    ([data-upload-area]); visual em public/css/app.css (.upload-area). O erro de validação
    do campo aparece logo abaixo.
--}}
@props([
    'name',
    'accept',
    'ilustracao' => 'generico',
    'titulo' => 'Arraste o arquivo aqui',
    'subtitulo' => 'ou clique para selecionar',
    'dica' => null,
    'obrigatorio' => true,
])

@php
    $id = $attributes->get('id', 'upload-'.$name);
    $idDica = $id.'-dica';
    $idErro = $id.'-erro';
    $comErro = $errors->has($name);
@endphp

<div {{ $attributes->except('id')->class(['upload-area', 'upload-area--erro' => $comErro]) }} data-upload-area data-ilustracao-area>
    <x-ilustracao :nome="$ilustracao" tamanho="sm" interativa class="upload-area__icon" />
    <div class="upload-area__text">
        <span class="upload-area__title">{{ $titulo }}</span>
        <span class="upload-area__subtitle">{{ $subtitulo }}</span>
    </div>
    <input type="file" name="{{ $name }}" id="{{ $id }}" class="upload-area__input" accept="{{ $accept }}"
        aria-label="{{ $titulo }}"
        @if ($dica || $comErro) aria-describedby="{{ trim(($dica ? $idDica : '').' '.($comErro ? $idErro : '')) }}" @endif
        @if ($comErro) aria-invalid="true" @endif
        @if ($obrigatorio) required @endif>
    <div class="upload-area__selected" hidden>
        <x-icon name="file-text" class="upload-area__file-icon" />
        <span class="upload-area__filename"></span>
        <button type="button" class="upload-area__remove" title="Remover arquivo" aria-label="Remover arquivo">&times;</button>
    </div>
    <span class="upload-area__aviso" role="alert" hidden></span>
</div>
@if ($dica)
    <p class="upload-area__dica" id="{{ $idDica }}">{{ $dica }}</p>
@endif
@error($name)
    <span class="form-error" id="{{ $idErro }}">{{ $message }}</span>
@enderror
