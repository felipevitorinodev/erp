@include('components.modal-cadastro-rapido', [
    'id' => 'modal-novo-cliente',
    'titulo' => 'Novo cliente',
    'rota' => route('api.clientes.store'),
    'btnLabel' => 'Salvar cliente',
    'campos' => [
        ['name' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true, 'maxlength' => 200],
        ['name' => 'cpf', 'label' => 'CPF', 'type' => 'text', 'maxlength' => 14],
        ['name' => 'cnpj', 'label' => 'CNPJ', 'type' => 'text', 'maxlength' => 18],
        ['name' => 'telefone', 'label' => 'Telefone', 'type' => 'text', 'maxlength' => 20],
        ['name' => 'email', 'label' => 'E-mail', 'type' => 'email', 'maxlength' => 100],
    ],
    'onSuccess' => 'function(data) {
        var input = document.getElementById("input-cliente");
        var hidden = document.getElementById("hidden-cliente-id");
        var nome = document.getElementById("hidden-cliente-nome");
        if (input) input.value = data.nome || "";
        if (hidden) hidden.value = data.id || "";
        if (nome) nome.value = data.nome || "";
    }',
])

@push('scripts')
<script>
(function () {
    var botao = document.getElementById('btn-add-cliente');
    if (!botao) return;
    botao.addEventListener('click', function (evento) {
        evento.preventDefault();
        evento.stopPropagation();
        if (typeof window.abrir_modal_novo_cliente === 'function') {
            window.abrir_modal_novo_cliente();
        }
    });
})();
</script>
@endpush

@push('styles')
<style>
.btn-add-inline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    margin-left: 0.35rem;
    padding: 0;
    border: 1px solid var(--color-primary);
    border-radius: 4px;
    background: var(--color-primary);
    color: white;
    font-size: 14px;
    font-weight: 600;
    line-height: 1;
    cursor: pointer;
    vertical-align: middle;
}
.btn-add-inline:hover {
    background: var(--color-accent);
    border-color: var(--color-accent);
}
</style>
@endpush
