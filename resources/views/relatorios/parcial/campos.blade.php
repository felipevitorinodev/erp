@foreach ($campos as $campo)
    @if ($campo['tipo'] === 'select')
        <div class="form-group">
            <label class="form-label" for="campo-{{ $campo['nome'] }}">{{ $campo['rotulo'] }}</label>
            <select class="form-control" name="{{ $campo['nome'] }}" id="campo-{{ $campo['nome'] }}">
                @if (! empty($campo['vazio']))
                    <option value="">{{ $campo['vazio'] }}</option>
                @endif
                @foreach ($campo['opcoes'] as $valor => $rotulo)
                    <option value="{{ $valor }}" @selected((string) ($campo['valor'] ?? '') === (string) $valor)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
    @elseif ($campo['tipo'] === 'busca')
        <div class="form-group">
            <label class="form-label" for="busca-{{ $campo['nome'] }}">{{ $campo['rotulo'] }}</label>
            <input
                type="text"
                class="form-control"
                id="busca-{{ $campo['nome'] }}"
                value="{{ $campo['texto'] }}"
                placeholder="{{ $campo['placeholder'] }}"
                autocomplete="off"
                data-busca-url="{{ $campo['url'] }}"
                data-busca-alvo="campo-{{ $campo['nome'] }}"
            >
            <input type="hidden" name="{{ $campo['nome'] }}" id="campo-{{ $campo['nome'] }}" value="{{ $campo['valor'] }}">
        </div>
    @elseif ($campo['tipo'] === 'checkbox')
        <x-filtro-campo type="checkbox" :name="$campo['nome']" :label="$campo['rotulo']" :checked="(bool) $campo['valor']" />
    @else
        <x-filtro-campo
            :label="$campo['rotulo']"
            :name="$campo['nome']"
            :value="$campo['valor'] ?? ''"
            :placeholder="$campo['placeholder'] ?? ''"
        />
    @endif
@endforeach
