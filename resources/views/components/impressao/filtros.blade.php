@props(['chips' => []])
@if($chips !== [])
    <div class="filters" aria-label="Filtros aplicados">
        @foreach($chips as $chip)
            <span class="chip">{{ $chip['rotulo'] }} <b>{{ $chip['valor'] }}</b></span>
        @endforeach
    </div>
@endif
