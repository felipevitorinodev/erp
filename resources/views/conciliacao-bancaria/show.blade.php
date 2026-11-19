@extends('layouts.app')

@section('title', 'Revisar extrato')
@section('page_title', 'Revisar extrato OFX')

@section('breadcrumb')
    <a href="{{ route('conciliacao-bancaria.index') }}">Conciliação bancária</a> / {{ $extrato->arquivo_nome }}
@endsection

@section('page_actions')
    <a href="{{ route('conciliacao-bancaria.index') }}" class="btn btn--ghost btn--sm">Voltar aos extratos</a>
@endsection

@section('content')
    <div class="card">
        <div class="card__header"><span class="card__title">{{ $extrato->arquivo_nome }}</span></div>
        <div class="card__body">
            <p>As entradas positivas podem ser vinculadas a contas a receber; as negativas, a contas a pagar. Transações ignoradas não geram lançamentos financeiros.</p>
        </div>
    </div>

    <x-tabela-card titulo="Transações" :paginator="$transacoes">
        <thead>
            <tr>
                <th>Data</th>
                <th>Descrição</th>
                <th class="col-num">Valor</th>
                <th>Situação</th>
                <th>Vínculo / ação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transacoes as $transacao)
                <tr>
                    <td data-label="Data">{{ $transacao->data_transacao->format('d/m/Y') }}</td>
                    <td data-label="Descrição">
                        {{ $transacao->descricao ?: '—' }}
                        @if ($transacao->memo)<span class="aviso-linha">{{ $transacao->memo }}</span>@endif
                    </td>
                    <td data-label="Valor" class="col-num {{ $transacao->valor < 0 ? 'text--danger' : '' }}">
                        <x-moeda :valor="$transacao->valor" />
                    </td>
                    <td data-label="Situação">
                        <x-badge-situacao tipo="conta" :valor="$transacao->situacao === 'conciliada' ? 'paga' : ($transacao->situacao === 'ignorada' ? 'cancelada' : 'aberta')" />
                        @if ($transacao->situacao === 'ignorada' && $transacao->motivo_ignorado)
                            <span class="aviso-linha">{{ $transacao->motivo_ignorado }}</span>
                        @endif
                    </td>
                    <td data-label="Vínculo / ação">
                        @if ($transacao->situacao === 'conciliada')
                            @if ($transacao->contaReceber)
                                <a href="{{ route('conta-receber.show', $transacao->contaReceber) }}">Conta a receber #{{ $transacao->conta_receber_id }}</a>
                            @elseif ($transacao->contaPagar)
                                <a href="{{ route('conta-pagar.show', $transacao->contaPagar) }}">Conta a pagar #{{ $transacao->conta_pagar_id }}</a>
                            @else
                                Registro conciliado
                            @endif
                        @elseif ($transacao->situacao === 'pendente' && $transacao->valor != 0)
                            <div class="conciliacao-acoes">
                                <input type="search" class="form-control js-busca-conta"
                                    placeholder="{{ $transacao->valor > 0 ? 'Buscar conta a receber' : 'Buscar conta a pagar' }}"
                                    data-url="{{ route('conciliacao-bancaria.contas') }}"
                                    data-tipo="{{ $transacao->valor > 0 ? 'receber' : 'pagar' }}"
                                    autocomplete="off">
                                <form method="POST" action="{{ route('conciliacao-bancaria.conciliar', $transacao) }}" class="js-form-conciliar">
                                    @csrf
                                    <select name="conta_id" class="form-control js-conta-result" required>
                                        <option value="">Digite ao menos 2 caracteres para buscar</option>
                                    </select>
                                    <button type="submit" class="btn btn--success btn--sm">Conciliar</button>
                                </form>
                                <form method="POST" action="{{ route('conciliacao-bancaria.ignorar', $transacao) }}" class="js-form-ignorar">
                                    @csrf
                                    <input type="text" name="motivo" class="form-control" maxlength="500" placeholder="Motivo (opcional)">
                                    <button type="submit" class="btn btn--ghost btn--sm">Ignorar</button>
                                </form>
                                <span class="form-hint js-busca-status" aria-live="polite"></span>
                            </div>
                        @else
                            <span class="text-muted">{{ $transacao->situacao === 'ignorada' ? 'Sem lançamento financeiro' : 'Transação sem valor conciliável' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-tabela-vazia ilustracao="carteira" :colspan="5" titulo="Nenhuma transação encontrada" texto="Este arquivo não contém transações novas." />
            @endforelse
        </tbody>
    </x-tabela-card>
@endsection

@push('scripts')
    <script>
        (function() {
            document.querySelectorAll('.js-busca-conta').forEach(function(input) {
                var linha = input.closest('.conciliacao-acoes');
                var select = linha.querySelector('.js-conta-result');
                var status = linha.querySelector('.js-busca-status');
                var timer;

                input.addEventListener('input', function() {
                    window.clearTimeout(timer);
                    var termo = input.value.trim();
                    if (termo.length < 2) {
                        select.innerHTML = '<option value="">Digite ao menos 2 caracteres para buscar</option>';
                        status.textContent = '';
                        return;
                    }

                    status.textContent = 'Buscando contas...';
                    timer = window.setTimeout(function() {
                        var url = new URL(input.dataset.url, window.location.origin);
                        url.searchParams.set('tipo', input.dataset.tipo);
                        url.searchParams.set('q', termo);
                        fetch(url, {headers: {'Accept': 'application/json'}})
                            .then(function(response) {
                                if (!response.ok) throw new Error('Falha ao buscar contas.');
                                return response.json();
                            })
                            .then(function(contas) {
                                select.innerHTML = '';
                                if (!contas.length) {
                                    select.innerHTML = '<option value="">Nenhuma conta aberta encontrada</option>';
                                    status.textContent = '';
                                    return;
                                }
                                select.innerHTML = '<option value="">Selecione uma conta</option>';
                                contas.forEach(function(conta) {
                                    var option = document.createElement('option');
                                    option.value = conta.id;
                                    option.textContent = conta.text;
                                    select.appendChild(option);
                                });
                                status.textContent = contas.length + ' conta(s) encontrada(s).';
                            })
                            .catch(function(error) {
                                select.innerHTML = '<option value="">Busca indisponível</option>';
                                status.textContent = error.message;
                            });
                    }, 250);
                });
            });

            document.querySelectorAll('.js-form-conciliar').forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!window.confirm('Registrar a baixa financeira e conciliar esta transação?')) {
                        event.preventDefault();
                    }
                });
            });
        })();
    </script>
@endpush
