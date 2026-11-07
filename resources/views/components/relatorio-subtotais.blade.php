@props([
    'itens' => null,
    'titulo' => 'Subtotais por categoria',
    'pagoLabel' => 'Pago',
])

@if($itens && $itens->isNotEmpty())
    <div {{ $attributes->class('card mt-2 subtotais') }}>
        <div class="card__header">
            <span class="card__title">{{ $titulo }}</span>
        </div>
        <ul class="subtotais__lista">
            @foreach($itens as $subtotal)
                @php
                    $valor = (float) $subtotal['soma_valor'];
                    $pago = (float) $subtotal['soma_pago'];
                    $percentual = $valor > 0 ? min(100, ($pago / $valor) * 100) : 0;
                @endphp
                <li class="subtotais__item">
                    <span class="subtotais__nome">{{ $subtotal['nome'] }}</span>
                    <span class="subtotais__valores">
                        <span>
                            <span class="subtotais__rotulo">Valor</span>
                            <span class="subtotais__cifra"><x-moeda :valor="$valor" /></span>
                        </span>
                        <span>
                            <span class="subtotais__rotulo">{{ $pagoLabel }}</span>
                            <span @class(['subtotais__cifra', 'subtotais__cifra--pago' => $pago > 0])><x-moeda :valor="$pago" /></span>
                        </span>
                    </span>
                    <span class="subtotais__barra" aria-hidden="true">
                        <span style="width: {{ number_format($percentual, 2, '.', '') }}%"></span>
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
