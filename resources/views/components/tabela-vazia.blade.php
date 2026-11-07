@props([
    'colspan' => 1,
])

<tr>
    <td colspan="{{ $colspan }}" {{ $attributes->class('empty-state') }}>
        {{ $slot }}
    </td>
</tr>
