{{--
    Códigos da NFS-e com autocompletar: serviço (cTribNac), código municipal, NBS, classificação IBS/CBS e indicador da operação.
    O NBS, a classificação e o indicador sugerem primeiro o que a correlação oficial do serviço escolhido traz;
    escolhido o serviço, os que estiverem em branco são preenchidos com a sugestão.
    prefixo: '' na emissão (c_trib_nac...) e 'nfse_' no produto e na empresa (nfse_c_trib_nac...).
--}}
@props([
    'prefixo' => '',
    'valores' => [],
    'obrigatorio' => false,
    'id' => 'nfse',
])

@php
    $campos = ['c_trib_nac' => 6, 'c_trib_mun' => 3, 'nbs' => 9, 'class_trib' => 6, 'ind_op' => 6];
    $codigos = [];
    foreach ($campos as $campo => $tamanho) {
        $digitos = preg_replace('/\D/', '', (string) old($prefixo.$campo, $valores[$campo] ?? '')) ?? '';
        $codigos[$campo] = strlen($digitos) === $tamanho ? $digitos : '';
    }
    $rotulos = \App\Services\Nfses\NfseCodigos::rotulos($codigos);
    $erro = fn (string $campo) => $errors->first($prefixo.$campo);
@endphp

<div {{ $attributes->class('nfse-codigos') }} data-nfse-codigos>
    <div class="form-grid form-grid--col-2">
        <div class="form-group">
            <label @class(['form-label', 'form-label--required' => $obrigatorio]) for="{{ $id }}-servico-busca">Código de tributação nacional</label>
            <input type="text" id="{{ $id }}-servico-busca" data-nfse-busca="servico" data-url="{{ route('api.nfse.servicos.busca') }}"
                @class(['form-control', 'is-invalid' => $erro('c_trib_nac')]) value="{{ $rotulos['c_trib_nac'] }}" autocomplete="off"
                placeholder="Ex.: 14.01 ou conserto">
            <input type="hidden" name="{{ $prefixo }}c_trib_nac" data-nfse-codigo="servico" value="{{ $codigos['c_trib_nac'] }}">
            @if ($erro('c_trib_nac'))
                <span class="form-error">{{ $erro('c_trib_nac') }}</span>
            @else
                <small class="text-muted">Subitem da lista da LC 116. Digite o código ou parte da descrição.</small>
            @endif
        </div>

        <div class="form-group">
            <label @class(['form-label', 'form-label--required' => $obrigatorio]) for="{{ $id }}-nbs-busca">NBS</label>
            <input type="text" id="{{ $id }}-nbs-busca" data-nfse-busca="nbs" data-url="{{ route('api.nfse.nbs.busca') }}"
                @class(['form-control', 'is-invalid' => $erro('nbs')]) value="{{ $rotulos['nbs'] }}" autocomplete="off"
                placeholder="Escolha o serviço antes">
            <input type="hidden" name="{{ $prefixo }}nbs" data-nfse-codigo="nbs" value="{{ $codigos['nbs'] }}">
            @if ($erro('nbs'))
                <span class="form-error">{{ $erro('nbs') }}</span>
            @else
                <small class="text-muted">Com o serviço escolhido, a lista traz os NBS da correlação oficial.</small>
            @endif
        </div>

        <div class="form-group">
            <label @class(['form-label', 'form-label--required' => $obrigatorio]) for="{{ $id }}-classificacao-busca">Classificação tributária IBS/CBS</label>
            <input type="text" id="{{ $id }}-classificacao-busca" data-nfse-busca="classificacao" data-url="{{ route('api.nfse.classificacoes.busca') }}"
                @class(['form-control', 'is-invalid' => $erro('class_trib')]) value="{{ $rotulos['class_trib'] }}" autocomplete="off"
                placeholder="Ex.: 000001">
            <input type="hidden" name="{{ $prefixo }}class_trib" data-nfse-codigo="classificacao" value="{{ $codigos['class_trib'] }}">
            @if ($erro('class_trib'))
                <span class="form-error">{{ $erro('class_trib') }}</span>
            @else
                <small class="text-muted">cClassTrib. 000001 é a tributação integral, sem redução.</small>
            @endif
        </div>

        <div class="form-group">
            <label class="form-label" for="{{ $id }}-indicador-busca">Indicador da operação</label>
            <input type="text" id="{{ $id }}-indicador-busca" data-nfse-busca="indicador" data-url="{{ route('api.nfse.indicadores.busca') }}"
                @class(['form-control', 'is-invalid' => $erro('ind_op')]) value="{{ $rotulos['ind_op'] }}" autocomplete="off"
                placeholder="Ex.: 050101">
            <input type="hidden" name="{{ $prefixo }}ind_op" data-nfse-codigo="indicador" value="{{ $codigos['ind_op'] }}">
            @if ($erro('ind_op'))
                <span class="form-error">{{ $erro('ind_op') }}</span>
            @else
                <small class="text-muted">cIndOp: onde o serviço é consumido. Vem da correlação do serviço.</small>
            @endif
        </div>

        <div class="form-group">
            <label class="form-label" for="{{ $id }}-c-trib-mun">Código municipal</label>
            <input type="text" id="{{ $id }}-c-trib-mun" name="{{ $prefixo }}c_trib_mun" maxlength="3" inputmode="numeric" autocomplete="off"
                @class(['form-control', 'is-invalid' => $erro('c_trib_mun')]) value="{{ $codigos['c_trib_mun'] }}" placeholder="3 dígitos">
            @if ($erro('c_trib_mun'))
                <span class="form-error">{{ $erro('c_trib_mun') }}</span>
            @else
                <small class="text-muted">Só se o município tiver desdobramento próprio do serviço. Em dúvida, deixe em branco.</small>
            @endif
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script src="{{ asset('js/nfse-codigos.js') }}?v={{ filemtime(public_path('js/nfse-codigos.js')) }}"></script>
    @endpush
@endonce
