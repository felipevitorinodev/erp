{{--
    Modal de Cadastro Rápido
    Uso: @include('components.modal-cadastro-rapido', [
        'id' => 'modal-novo-grupo',
        'titulo' => 'Novo Grupo',
        'rota' => route('api.grupos.store'),
        'campos' => [
            ['name' => 'nome', 'label' => 'Nome do Grupo', 'type' => 'text', 'required' => true],
            ['name' => 'descricao', 'label' => 'Descrição', 'type' => 'textarea'],
        ],
        'onSuccess' => 'function(data) { /* callback */ }'
    ])
--}}
@php
    $id = $id ?? 'modal-cadastro-rapido';
    $titulo = $titulo ?? 'Novo Cadastro';
    $rota = $rota ?? '';
    $campos = $campos ?? [];
    $onSuccess = $onSuccess ?? 'null';
    $btnLabel = $btnLabel ?? 'Salvar';
@endphp

<div class="modal-overlay modal-cadastro-rapido" id="{{ $id }}" hidden>
    <div class="modal-box">
        <div class="modal-box__header">
            <span class="card__title">{{ $titulo }}</span>
            <button type="button" class="btn btn--ghost btn--sm" data-modal-close>&times;</button>
        </div>
        <form class="form-cadastro-rapido" data-rota="{{ $rota }}">
            <div class="modal-box__body">
                <div class="modal-cadastro-rapido__erro" hidden></div>
                @foreach($campos as $campo)
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <label class="form-label {{ ($campo['required'] ?? false) ? 'form-label--required' : '' }}">
                            {{ $campo['label'] ?? $campo['name'] }}
                        </label>
                        @if(($campo['type'] ?? 'text') === 'textarea')
                            <textarea
                                name="{{ $campo['name'] }}"
                                class="form-control"
                                rows="{{ $campo['rows'] ?? 2 }}"
                                {{ ($campo['required'] ?? false) ? 'required' : '' }}
                                placeholder="{{ $campo['placeholder'] ?? '' }}"
                            ></textarea>
                        @elseif(($campo['type'] ?? 'text') === 'select')
                            <select
                                name="{{ $campo['name'] }}"
                                class="form-control"
                                {{ ($campo['required'] ?? false) ? 'required' : '' }}
                            >
                                <option value="">— Selecione —</option>
                                @foreach(($campo['options'] ?? []) as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input
                                type="{{ $campo['type'] ?? 'text' }}"
                                name="{{ $campo['name'] }}"
                                class="form-control"
                                {{ ($campo['required'] ?? false) ? 'required' : '' }}
                                placeholder="{{ $campo['placeholder'] ?? '' }}"
                                maxlength="{{ $campo['maxlength'] ?? 255 }}"
                            >
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="modal-box__footer">
                <button type="button" class="btn btn--ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn btn--primary">
                    <span class="btn-text">{{ $btnLabel }}</span>
                    <span class="btn-loading" hidden>Salvando...</span>
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var modal = document.getElementById('{{ $id }}');
    if (!modal) return;

    var form = modal.querySelector('form');
    var erroEl = modal.querySelector('.modal-cadastro-rapido__erro');
    var btnSubmit = form.querySelector('button[type="submit"]');
    var btnText = btnSubmit.querySelector('.btn-text');
    var btnLoading = btnSubmit.querySelector('.btn-loading');
    var onSuccessCallback = {!! $onSuccess !!};

    // Abrir modal
    window['abrir_{{ str_replace('-', '_', $id) }}'] = function() {
        form.reset();
        erroEl.hidden = true;
        modal.hidden = false;
        var firstInput = form.querySelector('input, textarea, select');
        if (firstInput) setTimeout(function() { firstInput.focus(); }, 100);
    };

    // Fechar modal
    modal.querySelectorAll('[data-modal-close]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modal.hidden = true;
        });
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) modal.hidden = true;
    });

    // Submit via AJAX
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        erroEl.hidden = true;
        btnSubmit.disabled = true;
        btnText.hidden = true;
        btnLoading.hidden = false;

        var formData = new FormData(form);
        var data = {};
        formData.forEach(function(value, key) { data[key] = value; });

        fetch(form.dataset.rota, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(function(response) {
            return response.json().then(function(json) {
                return { ok: response.ok, data: json };
            });
        })
        .then(function(result) {
            btnSubmit.disabled = false;
            btnText.hidden = false;
            btnLoading.hidden = true;

            if (result.ok && result.data.success !== false) {
                modal.hidden = true;
                if (typeof onSuccessCallback === 'function') {
                    onSuccessCallback(result.data);
                }
                // Dispara evento customizado
                document.dispatchEvent(new CustomEvent('{{ $id }}:success', { detail: result.data }));
            } else {
                var msg = result.data.message || result.data.error || 'Erro ao salvar. Verifique os dados.';
                if (result.data.errors) {
                    msg = Object.values(result.data.errors).flat().join('<br>');
                }
                erroEl.innerHTML = msg;
                erroEl.hidden = false;
            }
        })
        .catch(function(err) {
            btnSubmit.disabled = false;
            btnText.hidden = false;
            btnLoading.hidden = true;
            erroEl.textContent = 'Erro de conexão. Tente novamente.';
            erroEl.hidden = false;
        });
    });
})();
</script>
@endpush

@push('styles')
<style>
.modal-cadastro-rapido__erro {
    padding: 0.75rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    border-radius: 4px;
    color: #dc2626;
    font-size: 0.85rem;
    margin-bottom: 1rem;
}
</style>
@endpush
