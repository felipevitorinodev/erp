@extends('layouts.app')

@section('title', 'Revisar extrato')
@section('page_title', 'Revisar extrato OFX')

@section('breadcrumb')
    <a href="{{ route('conciliacao-bancaria.index') }}">Conciliação bancária</a> / {{ $extrato->arquivo_nome }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('conciliacao-bancaria.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>
    </div>
@endsection

@section('content')
    <x-ficha titulo="Extrato importado">
        <x-slot:resumo>
            <div><span>Pendentes</span><strong>{{ $resumo['pendente'] ?? 0 }}</strong></div>
            <div><span>Conciliadas</span><strong>{{ $resumo['conciliada'] ?? 0 }}</strong></div>
            <div><span>Ignoradas</span><strong>{{ $resumo['ignorada'] ?? 0 }}</strong></div>
        </x-slot:resumo>
        <x-ficha-campo label="Arquivo" largo>{{ $extrato->arquivo_nome }}</x-ficha-campo>
        <x-ficha-campo label="Conta bancária">{{ $extrato->conta_bancaria ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Período">
            {{ $extrato->data_inicio?->format('d/m/Y') ?? '—' }} a {{ $extrato->data_fim?->format('d/m/Y') ?? '—' }}
        </x-ficha-campo>
        <x-ficha-campo label="Importado em">{{ $extrato->created_at->format('d/m/Y H:i') }}</x-ficha-campo>
        <x-ficha-campo label="Como conciliar" largo class="ficha__item--texto">
            Créditos (valores positivos) baixam contas a receber; débitos (negativos), contas a pagar.
            Transações ignoradas não geram lançamento financeiro.
        </x-ficha-campo>
    </x-ficha>

    <x-tabela-card titulo="Transações" :paginator="$transacoes" class="mt-2">
        <x-slot:abas>
            <x-filtro-abas :opcoes="['pendente' => 'Pendentes', 'conciliada' => 'Conciliadas', 'ignorada' => 'Ignoradas']" />
        </x-slot:abas>
        <thead>
            <tr>
                <th>Data</th>
                <th class="col-titulo">Descrição</th>
                <th class="col-num">Valor</th>
                <th>Situação</th>
                <th>Vínculo / ação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($transacoes as $transacao)
                @php($credito = (float) $transacao->valor > 0)
                <tr>
                    <td data-label="Data">{{ $transacao->data_transacao->format('d/m/Y') }}</td>
                    <td data-label="Descrição" class="col-titulo">
                        {{ $transacao->descricao ?: '—' }}
                        @if ($transacao->memo && $transacao->memo !== $transacao->descricao)
                            <span class="conciliacao-memo">{{ $transacao->memo }}</span>
                        @endif
                    </td>
                    <td data-label="Valor" class="col-num {{ $credito ? '' : 'text--danger' }}">
                        <x-moeda :valor="$transacao->valor" />
                    </td>
                    <td data-label="Situação">
                        <x-badge-situacao tipo="transacao-bancaria" :valor="$transacao->situacao" />
                    </td>
                    <td data-label="Vínculo / ação" class="col-largo">
                        @if ($transacao->situacao === 'conciliada')
                            @if ($transacao->contaReceber)
                                <a href="{{ route('conta-receber.show', $transacao->contaReceber) }}">Conta a receber #{{ $transacao->conta_receber_id }}</a>
                            @elseif ($transacao->contaPagar)
                                <a href="{{ route('conta-pagar.show', $transacao->contaPagar) }}">Conta a pagar #{{ $transacao->conta_pagar_id }}</a>
                            @else
                                <span class="text-muted">Conta vinculada foi excluída</span>
                            @endif
                        @elseif ($transacao->situacao === 'pendente' && (float) $transacao->valor != 0)
                            <div class="conciliacao-acoes">
                                <form method="POST" action="{{ route('conciliacao-bancaria.conciliar', $transacao) }}" class="conciliacao-acoes__linha">
                                    @csrf
                                    <input type="search" class="form-control js-busca-conta"
                                        placeholder="{{ $credito ? 'Buscar conta a receber' : 'Buscar conta a pagar' }}"
                                        aria-label="{{ $credito ? 'Buscar conta a receber' : 'Buscar conta a pagar' }}"
                                        data-url="{{ route('conciliacao-bancaria.contas') }}"
                                        data-tipo="{{ $credito ? 'receber' : 'pagar' }}"
                                        autocomplete="off">
                                    <select name="conta_id" class="form-control js-conta-result" aria-label="Conta para conciliar" required>
                                        <option value="">Escolha a conta</option>
                                    </select>
                                    <button type="submit" class="btn btn--primary btn--sm"
                                        data-confirm="Registrar a baixa de R$ {{ number_format(abs((float) $transacao->valor), 2, ',', '.') }} na conta escolhida e conciliar esta transação?"
                                        data-confirm-title="Conciliar transação" data-confirm-ok="Conciliar">
                                        Conciliar
                                    </button>
                                    <span class="form-hint js-busca-status" aria-live="polite"></span>
                                </form>
                                <form method="POST" action="{{ route('conciliacao-bancaria.ignorar', $transacao) }}" class="conciliacao-acoes__linha">
                                    @csrf
                                    <input type="text" name="motivo" class="form-control" maxlength="500" placeholder="Motivo para ignorar (opcional)" aria-label="Motivo para ignorar">
                                    <button type="submit" class="btn btn--ghost btn--sm">Ignorar</button>
                                </form>
                            </div>
                        @elseif ($transacao->situacao === 'ignorada')
                            <span class="text-muted">Sem lançamento financeiro</span>
                            @if ($transacao->motivo_ignorado)
                                <span class="conciliacao-memo">Motivo: {{ $transacao->motivo_ignorado }}</span>
                            @endif
                        @else
                            <span class="text-muted">Transação sem valor conciliável</span>
                        @endif
                    </td>
                </tr>
            @empty
                <x-tabela-vazia ilustracao="carteira" :colspan="5" titulo="Nenhuma transação encontrada"
                    texto="Não há transações nesta situação." filtravel />
            @endforelse
        </tbody>
    </x-tabela-card>
@endsection

@push('scripts')
    <script>
        (function () {
            document.querySelectorAll('.js-busca-conta').forEach(function (input) {
                var linha = input.closest('.conciliacao-acoes__linha');
                var select = linha.querySelector('.js-conta-result');
                var status = linha.querySelector('.js-busca-status');
                var timer;

                function opcaoUnica(texto) {
                    select.innerHTML = '';
                    var option = document.createElement('option');
                    option.value = '';
                    option.textContent = texto;
                    select.appendChild(option);
                }

                input.addEventListener('input', function () {
                    window.clearTimeout(timer);
                    var termo = input.value.trim();
                    if (termo.length < 2) {
                        opcaoUnica('Escolha a conta');
                        status.textContent = termo.length ? 'Digite ao menos 2 caracteres para buscar.' : '';
                        return;
                    }

                    status.textContent = 'Buscando contas...';
                    timer = window.setTimeout(function () {
                        var url = new URL(input.dataset.url, window.location.origin);
                        url.searchParams.set('tipo', input.dataset.tipo);
                        url.searchParams.set('q', termo);
                        fetch(url, { headers: { 'Accept': 'application/json' } })
                            .then(function (response) {
                                if (!response.ok) throw new Error('Não foi possível buscar as contas agora.');
                                return response.json();
                            })
                            .then(function (contas) {
                                if (!contas.length) {
                                    opcaoUnica('Nenhuma conta aberta encontrada');
                                    status.textContent = '';
                                    return;
                                }
                                opcaoUnica('Selecione uma conta');
                                contas.forEach(function (conta) {
                                    var option = document.createElement('option');
                                    option.value = conta.id;
                                    option.textContent = conta.text;
                                    select.appendChild(option);
                                });
                                if (contas.length === 1) select.value = String(contas[0].id);
                                status.textContent = contas.length === 1 ? '1 conta encontrada.' : contas.length + ' contas encontradas.';
                            })
                            .catch(function (error) {
                                opcaoUnica('Busca indisponível');
                                status.textContent = error.message;
                            });
                    }, 250);
                });

                // Enter na busca não envia a conciliação sem conta escolhida.
                input.addEventListener('keydown', function (evento) {
                    if (evento.key === 'Enter') evento.preventDefault();
                });
            });
        })();
    </script>
@endpush
