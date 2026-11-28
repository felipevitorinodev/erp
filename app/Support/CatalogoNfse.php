<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Carrega as tabelas oficiais da NFS-e Nacional a partir de database/data/nfse/*.json.
 * Os JSON foram gerados dos anexos publicados em gov.br/nfse (Anexo A, Anexo B, Anexo VII da NT 009,
 * Anexo VIII de correlação e tabela de cClassTrib da RTC). Pode rodar de novo (comando nfse:catalogos):
 * atualiza pelo código e desativa o que saiu da tabela oficial, sem apagar.
 */
class CatalogoNfse
{
    /**
     * @return array<string, int> linhas gravadas por tabela
     */
    public static function sincronizar(): array
    {
        $agora = now();
        $total = [];

        $total['nfse_servicos'] = self::gravar('nfse_servicos', array_map(fn (array $linha) => [
            'codigo' => $linha['codigo'],
            'item' => $linha['item'],
            'descricao' => $linha['descricao'],
            'ativo' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::ler('servicos')), ['codigo'], ['item', 'descricao', 'ativo', 'updated_at'], true);

        $total['nfse_nbs'] = self::gravar('nfse_nbs', array_map(fn (array $linha) => [
            'codigo' => $linha['codigo'],
            'descricao' => mb_substr($linha['descricao'], 0, 500),
            'ativo' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::ler('nbs')), ['codigo'], ['descricao', 'ativo', 'updated_at'], true);

        $total['nfse_classificacoes'] = self::gravar('nfse_classificacoes', array_map(fn (array $linha) => [
            'codigo' => $linha['codigo'],
            'cst' => $linha['cst'],
            'cst_descricao' => mb_substr($linha['cst_descricao'], 0, 255),
            'descricao' => mb_substr($linha['descricao'], 0, 255),
            'exige_tributacao' => (bool) $linha['exige_tributacao'],
            'ativo' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::ler('classificacoes')), ['codigo'], ['cst', 'cst_descricao', 'descricao', 'exige_tributacao', 'ativo', 'updated_at'], true);

        $total['nfse_indicadores_operacao'] = self::gravar('nfse_indicadores_operacao', array_map(fn (array $linha) => [
            'codigo' => $linha['codigo'],
            'descricao' => mb_substr($linha['descricao'], 0, 500),
            'local' => $linha['local'] !== '' ? mb_substr($linha['local'], 0, 120) : null,
            'ativo' => true,
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::ler('indicadores_operacao')), ['codigo'], ['descricao', 'local', 'ativo', 'updated_at'], true);

        $total['nfse_correlacoes'] = self::gravar('nfse_correlacoes', array_map(fn (array $linha) => [
            'item' => $linha['item'],
            'nbs' => $linha['nbs'],
            'ind_op' => $linha['ind_op'],
            'class_trib' => $linha['class_trib'],
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::ler('correlacoes')), ['item', 'nbs'], ['ind_op', 'class_trib', 'updated_at']);

        $total['municipios'] = self::gravar('municipios', array_map(fn (array $linha) => [
            'codigo' => $linha['codigo'],
            'nome' => $linha['nome'],
            'uf' => $linha['uf'],
            'created_at' => $agora,
            'updated_at' => $agora,
        ], self::ler('municipios')), ['codigo'], ['nome', 'uf', 'updated_at']);

        return $total;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function ler(string $nome): array
    {
        $arquivo = database_path('data/nfse/'.$nome.'.json');
        $dados = is_file($arquivo) ? json_decode((string) file_get_contents($arquivo), true) : null;

        if (! is_array($dados)) {
            throw new RuntimeException('Tabela oficial da NFS-e ausente ou ilegível: '.$nome.'.json');
        }

        return $dados;
    }

    /**
     * @param  list<array<string, mixed>>  $linhas
     * @param  list<string>  $chave
     * @param  list<string>  $atualiza
     */
    private static function gravar(string $tabela, array $linhas, array $chave, array $atualiza, bool $desativarAusentes = false): int
    {
        foreach (array_chunk($linhas, 100) as $lote) {
            DB::table($tabela)->upsert($lote, $chave, $atualiza);
        }

        // Código que saiu da tabela oficial fica inativo (some do autocompletar), mas continua para as notas antigas.
        if ($desativarAusentes && $linhas !== []) {
            DB::table($tabela)
                ->whereNotIn('codigo', array_column($linhas, 'codigo'))
                ->where('ativo', true)
                ->update(['ativo' => false, 'updated_at' => now()]);
        }

        return count($linhas);
    }
}
