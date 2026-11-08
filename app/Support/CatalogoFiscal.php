<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Recorte das tabelas oficiais usado na busca da NF-e e do perfil fiscal.
 * Não substitui a TIPI nem o anexo do CEST inteiros: o que não estiver aqui continua digitável.
 */
class CatalogoFiscal
{
    public static function sincronizar(): void
    {
        $agora = now();

        self::gravar('cfops', self::linhas(self::cfops(), $agora), ['descricao', 'ativo', 'updated_at']);
        self::gravar('ncms', self::linhas(self::ncms(), $agora), ['descricao', 'ativo', 'updated_at']);
        self::gravar('cests', self::linhasCest($agora), ['ncm', 'descricao', 'ativo', 'updated_at']);
    }

    /**
     * @param  array<string, string>  $itens
     * @return list<array<string, mixed>>
     */
    private static function linhas(array $itens, $agora): array
    {
        $linhas = [];

        foreach ($itens as $codigo => $descricao) {
            $linhas[] = [
                'codigo' => $codigo,
                'descricao' => $descricao,
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        return $linhas;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function linhasCest($agora): array
    {
        $linhas = [];

        foreach (self::cests() as [$codigo, $ncm, $descricao]) {
            $linhas[] = [
                'codigo' => $codigo,
                'ncm' => $ncm,
                'descricao' => $descricao,
                'ativo' => true,
                'created_at' => $agora,
                'updated_at' => $agora,
            ];
        }

        return $linhas;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  list<string>  $atualiza
     */
    private static function gravar(string $tabela, array $linhas, array $atualiza): void
    {
        foreach (array_chunk($linhas, 40) as $lote) {
            DB::table($tabela)->upsert($lote, ['codigo'], $atualiza);
        }
    }

    /**
     * CFOP de saída interna usados na venda do Simples e do MEI.
     *
     * @return array<string, string>
     */
    public static function cfops(): array
    {
        return [
            '5101' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO',
            '5102' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS',
            '5103' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO EFETUADA FORA DO ESTABELECIMENTO',
            '5104' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS, EFETUADA FORA DO ESTABELECIMENTO',
            '5105' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO QUE NÃO DEVA POR ELE TRANSITAR',
            '5106' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS, QUE NÃO DEVA POR ELE TRANSITAR',
            '5109' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO DESTINADA À ZONA FRANCA DE MANAUS OU ÁREAS DE LIVRE COMÉRCIO',
            '5110' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS DESTINADA À ZONA FRANCA DE MANAUS OU ÁREAS DE LIVRE COMÉRCIO',
            '5111' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO REMETIDA ANTERIORMENTE EM CONSIGNAÇÃO INDUSTRIAL',
            '5112' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS REMETIDA ANTERIORMENTE EM CONSIGNAÇÃO INDUSTRIAL',
            '5113' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO REMETIDA ANTERIORMENTE EM CONSIGNAÇÃO MERCANTIL',
            '5114' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS REMETIDA ANTERIORMENTE EM CONSIGNAÇÃO MERCANTIL',
            '5115' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS, RECEBIDA ANTERIORMENTE EM CONSIGNAÇÃO MERCANTIL',
            '5116' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO ORIGINADA DE ENCOMENDA PARA ENTREGA FUTURA',
            '5117' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS, ORIGINADA DE ENCOMENDA PARA ENTREGA FUTURA',
            '5118' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO ENTREGUE AO DESTINATÁRIO POR CONTA E ORDEM DO ADQUIRENTE ORIGINÁRIO, EM VENDA À ORDEM',
            '5119' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS ENTREGUE AO DESTINATÁRIO POR CONTA E ORDEM DO ADQUIRENTE ORIGINÁRIO, EM VENDA À ORDEM',
            '5120' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS ENTREGUE AO DESTINATÁRIO PELO VENDEDOR REMETENTE, EM VENDA À ORDEM',
            '5122' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO REMETIDA PARA INDUSTRIALIZAÇÃO, POR CONTA E ORDEM DO ADQUIRENTE, SEM TRANSITAR PELO ESTABELECIMENTO DO ADQUIRENTE',
            '5123' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS REMETIDA PARA INDUSTRIALIZAÇÃO, POR CONTA E ORDEM DO ADQUIRENTE, SEM TRANSITAR PELO ESTABELECIMENTO DO ADQUIRENTE',
            '5124' => 'INDUSTRIALIZAÇÃO EFETUADA PARA OUTRA EMPRESA',
            '5125' => 'INDUSTRIALIZAÇÃO EFETUADA PARA OUTRA EMPRESA QUANDO A MERCADORIA RECEBIDA NÃO TRANSITA PELO ESTABELECIMENTO DO ADQUIRENTE',
            '5151' => 'TRANSFERÊNCIA DE PRODUÇÃO DO ESTABELECIMENTO',
            '5152' => 'TRANSFERÊNCIA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS',
            '5401' => 'VENDA DE PRODUÇÃO DO ESTABELECIMENTO EM OPERAÇÃO COM PRODUTO SUJEITO AO REGIME DE SUBSTITUIÇÃO TRIBUTÁRIA, NA CONDIÇÃO DE CONTRIBUINTE SUBSTITUTO',
            '5403' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS EM OPERAÇÃO COM MERCADORIA SUJEITA AO REGIME DE SUBSTITUIÇÃO TRIBUTÁRIA, NA CONDIÇÃO DE CONTRIBUINTE SUBSTITUTO',
            '5405' => 'VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS EM OPERAÇÃO COM MERCADORIA SUJEITA AO REGIME DE SUBSTITUIÇÃO TRIBUTÁRIA, NA CONDIÇÃO DE CONTRIBUINTE SUBSTITUÍDO',
            '5656' => 'VENDA DE COMBUSTÍVEL OU LUBRIFICANTE ADQUIRIDO OU RECEBIDO DE TERCEIROS DESTINADO A CONSUMIDOR OU USUÁRIO FINAL',
            '5901' => 'REMESSA PARA INDUSTRIALIZAÇÃO POR ENCOMENDA',
            '5904' => 'REMESSA PARA VENDA FORA DO ESTABELECIMENTO',
            '5908' => 'REMESSA DE BEM POR CONTA DE CONTRATO DE COMODATO',
            '5910' => 'REMESSA EM BONIFICAÇÃO, DOAÇÃO OU BRINDE',
            '5911' => 'REMESSA DE AMOSTRA GRÁTIS',
            '5915' => 'REMESSA DE MERCADORIA OU BEM PARA CONSERTO OU REPARO',
            '5927' => 'LANÇAMENTO EFETUADO A TÍTULO DE BAIXA DE ESTOQUE DECORRENTE DE PERDA, ROUBO OU DETERIORAÇÃO',
            '5929' => 'LANÇAMENTO EFETUADO EM DECORRÊNCIA DE EMISSÃO DE DOCUMENTO FISCAL RELATIVO A OPERAÇÃO TAMBÉM REGISTRADA EM EQUIPAMENTO ECF',
            '5949' => 'OUTRA SAÍDA DE MERCADORIA OU PRESTAÇÃO DE SERVIÇO NÃO ESPECIFICADO',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function ncms(): array
    {
        return [
            '02013000' => 'CARNES DE BOVINO DESOSSADAS, FRESCAS OU REFRIGERADAS',
            '04022110' => 'LEITE EM PÓ, SEM AÇÚCAR',
            '07133399' => 'FEIJÃO COMUM, EXCETO PARA SEMEADURA',
            '09012100' => 'CAFÉ TORRADO, NÃO DESCAFEINADO',
            '10063021' => 'ARROZ SEMIBRANQUEADO',
            '11010010' => 'FARINHA DE TRIGO',
            '17019900' => 'AÇÚCAR DE CANA',
            '19021900' => 'MASSAS ALIMENTÍCIAS NÃO COZIDAS',
            '19053100' => 'BISCOITOS E BOLACHAS',
            '19059090' => 'PÃES, BOLOS E PRODUTOS DE PADARIA',
            '20091900' => 'SUCO DE LARANJA',
            '21011110' => 'CAFÉ SOLÚVEL',
            '21069090' => 'PREPARAÇÕES ALIMENTÍCIAS DIVERSAS',
            '22011000' => 'ÁGUAS MINERAIS E ÁGUAS GASEIFICADAS',
            '22021000' => 'ÁGUAS, INCLUÍDAS AS MINERAIS, COM ADIÇÃO DE AÇÚCAR OU AROMATIZANTE',
            '22030000' => 'CERVEJAS DE MALTE',
            '22089000' => 'OUTRAS BEBIDAS ALCOÓLICAS',
            '27101259' => 'GASOLINA',
            '27101921' => 'ÓLEO DIESEL',
            '30049099' => 'MEDICAMENTOS',
            '33049910' => 'PRODUTOS DE BELEZA OU DE MAQUIAGEM',
            '33051000' => 'XAMPUS',
            '33072010' => 'DESODORANTES',
            '34011190' => 'SABÕES',
            '34022000' => 'DETERGENTES',
            '39241000' => 'SERVIÇOS DE MESA E UTENSÍLIOS DE PLÁSTICO',
            '48181000' => 'PAPEL HIGIÊNICO',
            '61091000' => 'CAMISETAS DE MALHA DE ALGODÃO',
            '61102000' => 'SUÉTERES E SEMELHANTES DE ALGODÃO',
            '62034200' => 'CALÇAS DE ALGODÃO',
            '64039900' => 'CALÇADOS',
            '84181000' => 'REFRIGERADORES',
            '84713012' => 'MÁQUINAS AUTOMÁTICAS PARA PROCESSAMENTO DE DADOS, PORTÁTEIS',
            '85094010' => 'LIQUIDIFICADORES',
            '85171231' => 'TELEFONES CELULARES',
            '85287200' => 'APARELHOS RECEPTORES DE TELEVISÃO',
        ];
    }

    /**
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function cests(): array
    {
        return [
            ['0300100', '22030000', 'CERVEJAS DE MALTE'],
            ['0301000', '22021000', 'REFRIGERANTES'],
            ['0301200', '22011000', 'ÁGUAS MINERAIS'],
            ['0600100', '27101259', 'GASOLINAS, EXCETO DE AVIAÇÃO'],
            ['0600601', '27101921', 'ÓLEO DIESEL'],
            ['2000100', '33049910', 'PRODUTOS DE PERFUMARIA OU DE TOUCADOR'],
            ['2002300', '33051000', 'XAMPUS'],
            ['2105300', '85171231', 'TELEFONES PARA REDES CELULARES'],
            ['2103700', '84713012', 'MÁQUINAS AUTOMÁTICAS PARA PROCESSAMENTO DE DADOS'],
            ['2106900', '85287200', 'APARELHOS RECEPTORES DE TELEVISÃO'],
            ['2103200', '84181000', 'REFRIGERADORES'],
            ['2104100', '85094010', 'LIQUIDIFICADORES'],
        ];
    }
}
