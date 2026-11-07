@props([
    'inicio' => '',
    'fim' => '',
    'nameInicio' => 'data_inicio',
    'nameFim' => 'data_fim',
    'labelInicio' => 'Data início',
    'labelFim' => 'Data fim',
])

<x-filtro-campo :label="$labelInicio" :name="$nameInicio" type="date" :value="$inicio" />
<x-filtro-campo :label="$labelFim" :name="$nameFim" type="date" :value="$fim" />
