@extends('layouts.app')

@section('title', 'Relatório — Contas a Receber')
@section('page_title', 'Contas a Receber')

@section('page_actions')
    <div class="page-actions">
        <x-export-dropdown
            :pdf="route('relatorio.contas-receber.pdf', request()->query())"
            :csv="route('relatorio.contas-receber.csv', request()->query())"
        />
    </div>
@endsection

@section('content')
    <x-relatorio-contas
        :action="route('relatorio.contas-receber')"
        pessoa="Cliente"
        relacao="cliente"
        busca-placeholder="Descrição ou cliente"
        recebido-label="Recebido"
        aberto-label="A receber"
        vazio="Nenhuma conta a receber no período."
        :filtros="$filtros"
        :categorias="$categorias"
        :totais="$totais"
        :contas="$contas"
        :subtotais="$subtotais_categoria"
    />
@endsection
