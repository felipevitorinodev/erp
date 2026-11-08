<?php

namespace App\Services\Nfes;

use App\Models\Empresas\Empresa;
use App\Models\Vendas\Venda;

/**
 * Confere, antes de confirmar a venda e consumir número, os dados que o NfeBuilderService recusaria.
 * As regras precisam acompanhar as do builder (CFOP série 5, CSOSN/CST sem cálculo, endereço do destinatário).
 */
class NfePendenciasService
{
    /**
     * @return list<string>
     */
    public function verificar(Venda $venda, Empresa $empresa): array
    {
        $venda->loadMissing(['itens.produto.perfilFiscal', 'cliente']);
        $vinculo = NfeVinculoFiscal::daEmpresa($empresa)->usarVenda($venda);
        $pendencias = [];

        if (blank($venda->natureza_operacao)) {
            $pendencias[] = 'Informe a natureza da operação.';
        }

        if ($venda->data_saida && $venda->data_venda && $venda->data_saida->copy()->startOfDay()->lt($venda->data_venda->copy()->startOfDay())) {
            $pendencias[] = 'A data de saída não pode ser anterior à data de emissão.';
        }

        if (blank($venda->forma_pagamento)) {
            $pendencias[] = 'Selecione a forma de pagamento.';
        }

        if ($venda->itens->isEmpty()) {
            $pendencias[] = 'Adicione pelo menos um item.';
        }

        foreach ($venda->itens as $item) {
            $produto = $item->produto;
            $nome = '"'.$item->produto_nome.'"';

            $ncm = $this->digitos($item->ncm ?: $produto?->ncm);
            if (strlen($ncm) !== 8) {
                $pendencias[] = "Item {$nome}: informe o NCM com 8 dígitos.";
            }

            $ean = $this->digitos($item->ean);
            if ($ean !== '' && $ean !== '0' && ! NfeVinculoFiscal::gtinValido($ean)) {
                $pendencias[] = "Item {$nome}: o código de barras é inválido. Corrija o EAN ou deixe em branco.";
            }

            $cfop = $this->digitos($vinculo->cfop($item, $produto)['valor']);
            if (strlen($cfop) !== 4 || ! str_starts_with($cfop, '5')) {
                $pendencias[] = "Item {$nome}: o CFOP precisa ter 4 dígitos e ser da série 5 (operação interna).";
            }

            $csosn = $this->digitos($vinculo->csosn($item, $produto)['valor']);
            if (! in_array($csosn, NfeBuilderService::CSOSN_SEM_CALCULO, true)) {
                $pendencias[] = "Item {$nome}: CSOSN {$csosn} não é aceito (use 102, 103, 300 ou 400).";
            }

            foreach (['cst_pis' => 'PIS', 'cst_cofins' => 'COFINS'] as $campo => $imposto) {
                $cst = $vinculo->cst($item, $produto, $campo, $campo, $campo, 'nfe_'.$campo.'_padrao')['valor'];
                if (! in_array($cst, NfeBuilderService::CST_SEM_INCIDENCIA, true)) {
                    $pendencias[] = "Item {$nome}: CST de {$imposto} {$cst} não é aceito (use 04 a 09).";
                }
            }

            if ((float) $item->quantidade <= 0) {
                $pendencias[] = "Item {$nome}: quantidade inválida.";
            }
        }

        if ((float) $venda->desconto > (float) $venda->itens->sum('total')) {
            $pendencias[] = 'O desconto é maior que o valor dos itens.';
        }

        $cliente = $venda->cliente;
        if ($cliente) {
            $endereco = [
                'logradouro' => trim((string) $cliente->logradouro),
                'número' => trim((string) $cliente->numero),
                'bairro' => trim((string) $cliente->bairro),
                'cidade' => trim((string) $cliente->cidade),
            ];
            $preenchidos = array_filter($endereco, fn ($v) => $v !== '');
            $documento = $this->digitos($cliente->cnpj) ?: $this->digitos($cliente->cpf);

            if ($documento !== '' && ! in_array(strlen($documento), [11, 14], true)) {
                $pendencias[] = 'O CPF/CNPJ do cliente "'.$cliente->nome.'" está incompleto; a nota sairia como consumidor final.';
            }

            if (count($preenchidos) === count($endereco)) {
                $cep = $this->digitos($cliente->cep ?: $empresa->cep);
                if (strlen($cep) !== 8) {
                    $pendencias[] = 'Informe o CEP do cliente com 8 dígitos. Sem CEP do cliente, vale o CEP da empresa, que também precisa ter 8 dígitos.';
                }

                $ufCliente = strtoupper(trim((string) $cliente->estado));
                if ($ufCliente !== '' && $ufCliente !== strtoupper((string) $empresa->uf)) {
                    $pendencias[] = 'O cliente é de outra UF; esta versão emite apenas operação interna.';
                } elseif (mb_strtolower($endereco['cidade']) !== mb_strtolower(trim((string) $empresa->cidade))) {
                    $pendencias[] = 'O cliente está em outro município; esta versão só emite para a cidade da empresa (ou deixe o endereço do cliente em branco).';
                }
            }
        }

        return array_values(array_unique($pendencias));
    }

    /**
     * Alíquota cadastrada que esta versão não envia. Não bloqueia a emissão.
     *
     * @return list<string>
     */
    public function avisosAliquota(Venda $venda): array
    {
        $venda->loadMissing('itens.produto');
        $avisos = [];

        foreach ($venda->itens as $item) {
            $produto = $item->produto;
            if (! $produto) {
                continue;
            }

            $nome = '"'.$item->produto_nome.'"';
            foreach ([
                'aliquota_icms' => 'ICMS',
                'aliquota_pis' => 'PIS',
                'aliquota_cofins' => 'COFINS',
            ] as $campo => $imposto) {
                if ((float) $produto->{$campo} > 0) {
                    $avisos[] = "Item {$nome}: o produto tem alíquota de {$imposto} cadastrada, mas o XML desta versão envia o imposto zerado.";
                }
            }
        }

        return $avisos;
    }

    private function digitos(mixed $valor): string
    {
        return preg_replace('/\D/', '', (string) $valor) ?? '';
    }
}
