@props([
    'label',
    'name' => null,
    'type' => 'text',
    'value' => '',
    'placeholder' => '',
    'options' => null,
    'vazio' => '— Todas —',
    'span' => null,
    'checked' => false,
    'principal' => null,
])

@php
    $fieldId = $attributes->get('id') ?: ($name ? 'campo-'.$name : null);
    // Campo principal (busca): continua visível no celular com os demais filtros recolhidos.
    $ehPrincipal = $principal ?? ($name === 'busca');
    $control = $attributes->merge(array_filter([
        'class' => 'form-control',
        'id' => $fieldId,
        'type' => $ehPrincipal && $type === 'text' ? 'search' : null,
        'enterkeyhint' => $ehPrincipal ? 'search' : null,
    ]));
@endphp

<div @class(['form-group', 'form-group--span-2' => (int) $span === 2, 'filtros__principal' => $ehPrincipal])>
    @if($type === 'checkbox')
        <label class="form-check" @if($fieldId) for="{{ $fieldId }}" @endif>
            <input type="checkbox" name="{{ $name }}" value="1" @if($fieldId) id="{{ $fieldId }}" @endif @checked($checked)>
            <span>{{ $label }}</span>
        </label>
    @else
        <label class="form-label" @if($fieldId) for="{{ $fieldId }}" @endif>{{ $label }}</label>
        @if($slot->isNotEmpty())
            {{ $slot }}
        @elseif($type === 'select')
            <select name="{{ $name }}" {{ $control }}>
                @if($vazio !== false && $vazio !== null)
                    <option value="">{{ $vazio }}</option>
                @endif
                @foreach($options ?? [] as $opcaoValor => $opcaoLabel)
                    <option value="{{ $opcaoValor }}" {{ (string) $value === (string) $opcaoValor ? 'selected' : '' }}>
                        {{ $opcaoLabel }}
                    </option>
                @endforeach
            </select>
        @else
            <input @unless($control->has('type')) type="{{ $type }}" @endunless name="{{ $name }}" value="{{ $value }}"
                placeholder="{{ $placeholder }}" {{ $control }}>
        @endif
    @endif
</div>
