@extends('layouts.app')

@section('title', 'Relatório — Contas a Pagar')
@section('page_title', 'Contas a Pagar')

@section('page_actions')
    <div class="page-actions">
        <x-export-dropdown
            :pdf="route('relatorio.contas-pagar.pdf', request()->query())"
            :csv="route('relatorio.contas-pagar.csv', request()->query())"
        />
    </div>
@endsection

@section('content')
    <x-relatorio-contas
        :action="route('relatorio.contas-pagar')"
        pessoa="Fornecedor"
        relacao="fornecedor"
        busca-placeholder="Descrição ou fornecedor"
        recebido-label="Pago"
        aberto-label="A pagar"
        vazio="Nenhuma conta a pagar no período."
        :filtros="$filtros"
        :categorias="$categorias"
        :totais="$totais"
        :contas="$contas"
        :subtotais="$subtotais_categoria"
    />
@endsection
