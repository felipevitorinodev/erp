@php
    // expected variables: name, value, id (optional), class (optional), decimals (optional, default 2), placeholder (optional), required (optional)
    $decimals = isset($decimals) ? (int)$decimals : 2;
    $attrs = $attributes ?? [];
@endphp
<input
    type="tel"
    name="{{ $name ?? '' }}"
    id="{{ $id ?? '' }}"
    value="{{ $value ?? '' }}"
    class="form-control input-numeric {{ $class ?? '' }}"
    inputmode="decimal"
    data-decimals="{{ $decimals }}"
    placeholder="{{ $placeholder ?? '' }}"
    {{ isset($required) && $required ? 'required' : '' }}
    @foreach($attrs as $k => $v) {!! $k.'=\"'.$v.'\"' !!} @endforeach
/>

