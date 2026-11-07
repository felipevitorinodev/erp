<?php

namespace App\Impressao;

use App\Models\ContasReceber\ContaReceber;
use App\Models\Orcamentos\Orcamento;
use App\Models\OrdensServico\OrdemServico;
use App\Models\Vendas\Venda;
use Illuminate\Support\Collection;

class CupomDocumento
{
    public static function venda(Venda $venda): array
    {
        $venda->loadMissing(['cliente', 'itens', 'formaPagamento']);
        $forma = self::forma($venda);
        $pago = self::faixaPagamento((string) $venda->situacao, $forma);

        return self::operacao(
            'COMPROVANTE DE VENDA',
            self::numero($venda->numero),
            $venda->data_venda,
            $venda->created_at,
            $venda->itens,
            (float) $venda->subtotal,
            (float) $venda->desconto,
            (float) $venda->acrescimo,
            (float) $venda->total,
            $forma,
            $pago,
            $venda->cliente->nome ?? null,
        );
    }

    public static function orcamento(Orcamento $orcamento): array
    {
        $orcamento->loadMissing(['cliente', 'itens']);
        $situacoes = [
            'pendente' => 'EM ABERTO',
            'aprovado' => 'APROVADO',
            'recusado' => 'RECUSADO',
            'cancelado' => 'CANCELADO',
        ];

        return self::operacao(
            'COMPROVANTE DE ORÇAMENTO',
            self::numero($orcamento->numero),
            $orcamento->data_orcamento,
            $orcamento->created_at,
            $orcamento->itens,
            (float) $orcamento->subtotal,
            (float) $orcamento->desconto,
            (float) $orcamento->acrescimo,
            (float) $orcamento->total,
            self::forma($orcamento),
            $situacoes[$orcamento->situacao] ?? mb_strtoupper((string) $orcamento->situacao),
            $orcamento->cliente->nome ?? null,
            $orcamento->data_entrega
                ? [['Validade', $orcamento->data_entrega->format('d/m/Y')]]
                : [],
        );
    }

    public static function ordemServico(OrdemServico $os): array
    {
        $os->loadMissing(['cliente', 'itens', 'tecnico']);
        $equipamento = trim(implode(' ', array_filter([$os->equipamento, $os->marca, $os->modelo])));
        $extras = array_values(array_filter([
            $equipamento !== '' ? ['Equipamento', $equipamento] : null,
            $os->tecnico ? ['Técnico', $os->tecnico->nome] : null,
        ]));

        return self::operacao(
            'ORDEM DE SERVIÇO',
            self::numero($os->numero),
            $os->data_abertura,
            $os->created_at,
            $os->itens,
            (float) $os->subtotal,
            (float) $os->desconto,
            (float) $os->acrescimo,
            (float) $os->total,
            self::forma($os),
            mb_strtoupper($os->situacaoLabel()),
            $os->cliente->nome ?? null,
            $extras,
        );
    }

    public static function reciboConta(ContaReceber $conta): array
    {
        $conta->loadMissing(['cliente', 'baixas', 'venda']);
        $referente = $conta->descricao ?: 'Conta a receber';
        if ($conta->venda && ! str_contains($referente, (string) $conta->venda->numero)) {
            $referente .= ' · Venda Nº '.self::numero($conta->venda->numero);
        }

        return self::recibo(
            $referente,
            $conta->cliente->nome ?? null,
            $conta->baixas,
            (float) $conta->valor,
            (float) $conta->valor_pago,
            'Conta Nº '.self::numero($conta->id).' · '.now()->format('d/m/Y · H:i'),
        );
    }

    public static function dinheiro(float|int|string|null $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }

    public static function numero(mixed $numero): string
    {
        $texto = trim((string) $numero);
        if ($texto !== '' && ctype_digit($texto)) {
            return str_pad($texto, 6, '0', STR_PAD_LEFT);
        }

        return $texto !== '' ? $texto : '—';
    }

    public static function quantidade(float|int|string|null $valor): string
    {
        $numero = (float) $valor;
        if (abs($numero - round($numero)) < 0.0001) {
            return (string) (int) round($numero);
        }

        return rtrim(rtrim(number_format($numero, 3, ',', '.'), '0'), ',');
    }

    /**
     * @param  iterable<int, mixed>  $itens
     * @param  list<array{0: string, 1: string}>  $extras
     * @return array<string, mixed>
     */
    private static function operacao(
        string $titulo,
        string $numero,
        mixed $data,
        mixed $criadoEm,
        iterable $itens,
        float $subtotal,
        float $desconto,
        float $acrescimo,
        float $total,
        string $forma,
        string $pago,
        ?string $cliente,
        array $extras = [],
    ): array {
        $linhasItens = [];
        $quantidade = 0.0;
        foreach ($itens as $item) {
            $quantidade += (float) $item->quantidade;
            $detalhe = self::quantidade($item->quantidade).' un × R$ '.self::dinheiro($item->preco_unitario);
            if ((float) $item->desconto > 0) {
            $detalhe .= ' - '.self::dinheiro($item->desconto);
            }
            $linhasItens[] = [
                'nome' => $item->produto_nome,
                'valor' => self::dinheiro($item->total),
                'detalhe' => $detalhe,
            ];
        }

        $ajustes = [];
        if ($desconto > 0) {
            $ajustes[] = ['Desconto', '- '.self::dinheiro($desconto)];
        }
        if ($acrescimo > 0) {
            $ajustes[] = ['Acréscimo', '+ '.self::dinheiro($acrescimo)];
        }

        $pagamentos = $forma !== ''
            ? [[$forma, self::dinheiro($total)]]
            : [];

        return self::base([
            'titulo' => $titulo,
            'subtitulo' => 'Nº '.$numero.' · '.self::quando($data, $criadoEm),
            'itens' => $linhasItens,
            'linhas' => [['Subtotal ('.self::quantidade($quantidade).' un)', self::dinheiro($subtotal)]],
            'ajustes' => $ajustes,
            'total' => 'R$ '.self::dinheiro($total),
            'pagamentos' => $pagamentos,
            'pago' => $pago,
            'extras' => array_merge([['Cliente', $cliente ?: 'Consumidor final']], $extras),
            'agradecimento' => 'Obrigado pela preferência!',
            'mensagem' => 'Volte sempre',
        ]);
    }

    /**
     * @param  Collection<int, mixed>|iterable<int, mixed>  $baixas
     * @return array<string, mixed>
     */
    private static function recibo(
        string $referente,
        ?string $cliente,
        iterable $baixas,
        float $valor,
        float $pago,
        string $subtitulo,
    ): array {
        $itens = [];
        foreach ($baixas as $baixa) {
            $data = $baixa->data_pagamento ?? null;
            $itens[] = [
                'nome' => $baixa->forma_pagamento ?: 'Recebimento',
                'valor' => self::dinheiro($baixa->valor),
                'detalhe' => $data ? $data->format('d/m/Y') : $referente,
            ];
        }
        if ($itens === []) {
            $itens[] = [
                'nome' => 'Nenhum recebimento',
                'valor' => self::dinheiro(0),
                'detalhe' => 'Valor em aberto',
            ];
        }

        $saldo = max(0, round($valor - $pago, 2));

        return self::base([
            'titulo' => 'RECIBO',
            'subtitulo' => $subtitulo,
            'itens' => $itens,
            'linhas' => [
                ['Valor', self::dinheiro($valor)],
                ['Recebido', self::dinheiro($pago)],
                ['Saldo', self::dinheiro($saldo)],
            ],
            'ajustes' => [],
            'total' => 'R$ '.self::dinheiro($pago),
            'pagamentos' => [],
            'pago' => $pago > 0 ? 'RECEBIDO' : 'SEM RECEBIMENTO',
            'extras' => [
                ['Cliente', $cliente ?: 'Consumidor final'],
                ['Referente', $referente],
            ],
            'agradecimento' => 'Recibo de recebimento de contas',
            'mensagem' => 'Documento sem valor fiscal',
        ]);
    }

    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private static function base(array $dados): array
    {
        return $dados;
    }

    private static function aPrazo(string $forma): bool
    {
        return preg_match('/prazo/iu', $forma) === 1;
    }

    private static function faixaPagamento(string $situacao, string $forma): string
    {
        $rotulo = mb_strtoupper($forma ?: 'DINHEIRO');

        return match ($situacao) {
            'confirmada' => self::aPrazo($forma) ? $rotulo : 'PAGO EM '.$rotulo,
            'cancelada' => 'CANCELADA',
            default => 'EM ANDAMENTO',
        };
    }

    private static function forma(object $documento): string
    {
        $forma = $documento->forma_pagamento ?? null;
        if (is_string($forma) && trim($forma) !== '') {
            return trim($forma);
        }
        if (is_object($forma) && isset($forma->nome) && trim((string) $forma->nome) !== '') {
            return trim((string) $forma->nome);
        }
        if (method_exists($documento, 'formaPagamento') && $documento->relationLoaded('formaPagamento') && $documento->formaPagamento) {
            return trim((string) $documento->formaPagamento->nome);
        }

        return '';
    }

    private static function quando(mixed $data, mixed $criadoEm): string
    {
        $texto = $data ? $data->format('d/m/Y') : now()->format('d/m/Y');
        if ($criadoEm) {
            $texto .= ' · '.$criadoEm->timezone(config('app.timezone'))->format('H:i');
        }

        return $texto;
    }
}
