<?php

namespace App\Services\Nfses;

use App\Models\Empresas\Empresa;
use App\Models\Fiscal\NfseClassificacao;
use App\Models\Fiscal\NfseCorrelacao;
use App\Models\Fiscal\NfseIndicadorOperacao;
use App\Models\Fiscal\NfseNbs;
use App\Models\Fiscal\NfseServico;
use App\Models\Vendas\VendaItem;
use Illuminate\Support\Collection;

/**
 * Códigos da NFS-e de uma venda, na ordem: o que ficou gravado no produto (última emissão ou cadastro),
 * o padrão da empresa e, por fim, a correlação oficial (Anexo VIII) item × NBS × cIndOp × cClassTrib.
 * A NFS-e leva um único código de tributação nacional: serviços com códigos diferentes são barrados.
 */
class NfseCodigos
{
    public const CAMPOS = ['c_trib_nac', 'c_trib_mun', 'nbs', 'class_trib', 'ind_op'];

    /**
     * @param  Collection<int, VendaItem>  $itens  itens de serviço da venda
     * @return array{codigos: array<string, string>, bloqueio: string|null}
     */
    public static function sugerir(Collection $itens, Empresa $empresa): array
    {
        $porCodigo = [];
        foreach ($itens as $item) {
            $codigo = self::digitos($item->produto?->nfse_c_trib_nac, 6);
            if ($codigo !== '') {
                $porCodigo[$codigo][] = $item->produto_nome;
            }
        }

        if (count($porCodigo) > 1) {
            $partes = [];
            foreach ($porCodigo as $codigo => $nomes) {
                $partes[] = self::formatarServico($codigo).' ('.implode(', ', array_unique($nomes)).')';
            }

            return [
                'codigos' => array_fill_keys(self::CAMPOS, ''),
                'bloqueio' => 'Os serviços desta venda têm códigos de tributação diferentes: '.implode('; ', $partes)
                    .'. A NFS-e leva um código por nota. Separe os serviços em vendas diferentes ou acerte o código no cadastro do produto.',
            ];
        }

        $cTribNac = array_key_first($porCodigo) ?? self::digitos($empresa->nfse_c_trib_nac, 6);
        $codigos = ['c_trib_nac' => (string) $cTribNac, 'c_trib_mun' => '', 'nbs' => '', 'class_trib' => '', 'ind_op' => ''];

        $produtos = $itens->map(fn (VendaItem $item) => $item->produto)->filter();
        $mesmoCodigo = $produtos->filter(fn ($produto) => self::digitos($produto->nfse_c_trib_nac, 6) === $codigos['c_trib_nac']);
        $tamanhos = ['c_trib_mun' => 3, 'nbs' => 9, 'class_trib' => 6, 'ind_op' => 6];

        foreach ($tamanhos as $campo => $tamanho) {
            $codigos[$campo] = (string) ($mesmoCodigo
                ->map(fn ($produto) => self::digitos($produto->{'nfse_'.$campo}, $tamanho))
                ->first(fn (string $valor) => $valor !== '') ?? '');
        }

        $padraoEmpresa = self::digitos($empresa->nfse_c_trib_nac, 6);
        if ($padraoEmpresa === '' || $padraoEmpresa === $codigos['c_trib_nac']) {
            foreach ($tamanhos as $campo => $tamanho) {
                if ($codigos[$campo] === '') {
                    $codigos[$campo] = self::digitos($empresa->{'nfse_'.$campo}, $tamanho);
                }
            }
        }

        return ['codigos' => self::completarPelaCorrelacao($codigos), 'bloqueio' => null];
    }

    /**
     * @param  array<string, string>  $codigos
     * @return array<string, string>
     */
    public static function completarPelaCorrelacao(array $codigos): array
    {
        if (strlen($codigos['c_trib_nac']) !== 6) {
            return $codigos;
        }

        $correlacoes = NfseCorrelacao::query()->where('item', self::item($codigos['c_trib_nac']))->get();
        if ($correlacoes->isEmpty()) {
            return $codigos;
        }

        if ($codigos['nbs'] === '' && $correlacoes->count() === 1) {
            $codigos['nbs'] = (string) $correlacoes->first()->nbs;
        }

        $base = $codigos['nbs'] !== ''
            ? $correlacoes->where('nbs', $codigos['nbs'])
            : $correlacoes;

        foreach (['ind_op' => 'ind_op', 'class_trib' => 'class_trib'] as $campo => $coluna) {
            if ($codigos[$campo] !== '') {
                continue;
            }
            $valores = $base->pluck($coluna)->filter()->unique()->values();
            if ($valores->count() === 1) {
                $codigos[$campo] = (string) $valores->first();
            }
        }

        return $codigos;
    }

    /** Subitem da lista no formato da correlação: "14.01". */
    public static function item(string $cTribNac): string
    {
        return substr($cTribNac, 0, 2).'.'.substr($cTribNac, 2, 2);
    }

    public static function formatarServico(string $codigo): string
    {
        return strlen($codigo) === 6
            ? substr($codigo, 0, 2).'.'.substr($codigo, 2, 2).'.'.substr($codigo, 4, 2)
            : $codigo;
    }

    public static function formatarNbs(string $codigo): string
    {
        return strlen($codigo) === 9
            ? substr($codigo, 0, 1).'.'.substr($codigo, 1, 4).'.'.substr($codigo, 5, 2).'.'.substr($codigo, 7, 2)
            : $codigo;
    }

    /**
     * Rótulos "código - descrição" para preencher os campos de busca da tela.
     *
     * @param  array<string, string>  $codigos
     * @return array<string, string>
     */
    public static function rotulos(array $codigos): array
    {
        $rotulo = function (string $codigo, ?string $descricao, string $exibido): string {
            if ($codigo === '') {
                return '';
            }

            return $descricao ? $exibido.' - '.$descricao : $codigo;
        };

        $servico = $codigos['c_trib_nac'] !== '' ? NfseServico::query()->where('codigo', $codigos['c_trib_nac'])->value('descricao') : null;
        $nbs = $codigos['nbs'] !== '' ? NfseNbs::query()->where('codigo', $codigos['nbs'])->value('descricao') : null;
        $classe = $codigos['class_trib'] !== '' ? NfseClassificacao::query()->where('codigo', $codigos['class_trib'])->value('descricao') : null;
        $indicador = $codigos['ind_op'] !== '' ? NfseIndicadorOperacao::query()->where('codigo', $codigos['ind_op'])->value('descricao') : null;

        return [
            'c_trib_nac' => $rotulo($codigos['c_trib_nac'], $servico, self::formatarServico($codigos['c_trib_nac'])),
            'nbs' => $rotulo($codigos['nbs'], $nbs, self::formatarNbs($codigos['nbs'])),
            'class_trib' => $rotulo($codigos['class_trib'], $classe, $codigos['class_trib']),
            'ind_op' => $rotulo($codigos['ind_op'], $indicador, $codigos['ind_op']),
        ];
    }

    public static function digitos(mixed $valor, int $tamanho): string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        return strlen($digitos) === $tamanho ? $digitos : '';
    }
}
