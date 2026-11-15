<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cache entre telas com invalidação por versão (chaves geracionais).
 *
 * Cada empresa tem uma versão por domínio (vendas, financeiro, estoque...). Toda escrita (insert, update,
 * delete) numa tabela do domínio troca a versão depois do commit, e as chaves antigas deixam de ser lidas.
 * Assim a tela reaproveita o que outra tela já calculou (dashboard, totais de relatório, contagens da
 * paginação, listas de apoio) e nunca mostra dado anterior a uma gravação já confirmada.
 *
 * A troca de versão é feita depois do commit: um leitor que calcular antes do commit grava o resultado na
 * versão antiga, que ninguém mais lê. Escrita sem usuário logado (console, fila) troca a versão global.
 */
final class CacheDados
{
    /** Tabela => domínio. Tabela fora da lista (sessões, cache, filas) não invalida nada. */
    private const DOMINIOS = [
        'vendas' => 'vendas',
        'venda_itens' => 'vendas',
        'comissoes' => 'vendas',
        'nfes' => 'vendas',
        'nfe_numeracoes' => 'vendas',
        'nfe_inutilizacoes' => 'vendas',
        'contas_receber' => 'financeiro',
        'conta_receber_baixas' => 'financeiro',
        'contas_pagar' => 'financeiro',
        'conta_pagar_baixas' => 'financeiro',
        'categorias_financeiras' => 'financeiro',
        'produtos' => 'estoque',
        'movimentacoes_estoque' => 'estoque',
        'entradas_estoque' => 'estoque',
        'entrada_estoque_itens' => 'estoque',
        'grupos' => 'estoque',
        'unidades_medida' => 'estoque',
        'perfis_fiscais' => 'estoque',
        'clientes' => 'cadastros',
        'fornecedores' => 'cadastros',
        'funcionarios' => 'cadastros',
        'formas_pagamento' => 'cadastros',
        'orcamentos' => 'servicos',
        'orcamento_itens' => 'servicos',
        'ordens_servico' => 'servicos',
        'ordem_servico_itens' => 'servicos',
        'empresa' => 'empresa',
        'perfis' => 'acesso',
        'users' => 'acesso',
    ];

    private const PREFIXO = 'dados:';

    /**
     * Listas maiores que isto não são guardadas: com o cache em banco (CACHE_STORE=database), serializar e
     * reler dezenas de milhares de linhas custa mais do que refazer a consulta agregada pelo índice.
     */
    public const LIMITE_ITENS = 5000;

    /** @var array<string, true> versões a trocar no próximo commit */
    private static array $pendentes = [];

    /** @var array<string, mixed> versões já lidas nesta requisição */
    private static array $lidas = [];

    /**
     * Devolve o valor guardado para (nome, parâmetros) na versão atual dos domínios ou calcula e guarda.
     * Se o cache falhar, calcula direto: o cache nunca pode derrubar a tela.
     *
     * @template T
     *
     * @param  list<string>  $dominios
     * @param  array<array-key, mixed>  $parametros  tudo que muda o resultado (filtros, data de hoje, permissões)
     * @param  Closure(): T  $calcular
     * @return T
     */
    public static function lembrar(string $nome, array $dominios, array $parametros, Closure $calcular, int $segundos = 600, ?int $empresaId = null): mixed
    {
        $empresaId ??= self::empresaAtual();

        try {
            $versoes = self::versoes($dominios, $empresaId);
            $chave = self::PREFIXO.$nome.':'.$empresaId.':'.md5((string) json_encode([$versoes, $parametros]));

            $guardado = Cache::get($chave);
            if (is_array($guardado) && array_key_exists('v', $guardado)) {
                return $guardado['v'];
            }
        } catch (Throwable $e) {
            report($e);

            return $calcular();
        }

        $valor = $calcular();
        if (is_array($valor) && count($valor) > self::LIMITE_ITENS) {
            return $valor;
        }

        try {
            // Embrulhado para distinguir "não guardado" de um resultado null.
            Cache::put($chave, ['v' => $valor], $segundos);
        } catch (Throwable $e) {
            report($e);
        }

        return $valor;
    }

    /**
     * Ouvinte de DB::listen: marca o domínio da tabela escrita e troca a versão depois do commit.
     */
    public static function registrarEscrita(QueryExecuted $consulta): void
    {
        $sql = ltrim($consulta->sql);
        $inicio = strtolower(substr($sql, 0, 7));
        if ($inicio !== 'insert ' && $inicio !== 'update ' && $inicio !== 'delete ' && $inicio !== 'replace') {
            return;
        }

        if (! preg_match('/^(?:insert(?:\s+ignore)?\s+into|replace\s+into|update(?:\s+ignore)?|delete\s+from)\s+[`"\[]?(\w+)/i', $sql, $partes)) {
            return;
        }

        $dominio = self::DOMINIOS[strtolower($partes[1])] ?? null;
        if ($dominio === null) {
            return;
        }

        self::$pendentes[$dominio.':'.self::empresaAtual()] = true;
        self::$lidas = [];

        try {
            DB::connection($consulta->connectionName)->afterCommit(fn () => self::publicar());
        } catch (Throwable) {
            self::publicar();
        }
    }

    /**
     * Troca agora as versões marcadas (usado no afterCommit e quando não há transação aberta).
     */
    public static function publicar(): void
    {
        if (self::$pendentes === []) {
            return;
        }

        $novas = [];
        $marca = bin2hex(random_bytes(6));
        foreach (array_keys(self::$pendentes) as $alvo) {
            [$dominio, $empresaId] = explode(':', $alvo);
            $chave = (int) $empresaId > 0 ? self::chaveVersao($dominio, (int) $empresaId) : self::chaveGlobal();
            $novas[$chave] = $marca;
        }

        self::$pendentes = [];
        self::$lidas = [];

        try {
            Cache::putMany($novas, 60 * 60 * 24 * 365);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  list<string>  $dominios
     * @return array<string, mixed>
     */
    private static function versoes(array $dominios, int $empresaId): array
    {
        $chaves = [self::chaveGlobal()];
        foreach ($dominios as $dominio) {
            $chaves[] = self::chaveVersao($dominio, $empresaId);
        }

        if (array_diff($chaves, array_keys(self::$lidas)) !== []) {
            // Uma leitura só traz todas as versões da empresa: as próximas telas da requisição não consultam de novo.
            $todas = [self::chaveGlobal()];
            foreach (array_unique(self::DOMINIOS) as $dominio) {
                $todas[] = self::chaveVersao($dominio, $empresaId);
            }
            self::$lidas = Cache::many(array_values(array_unique(array_merge($todas, $chaves)))) + self::$lidas;
        }

        $versoes = [];
        foreach ($chaves as $chave) {
            $versoes[$chave] = self::$lidas[$chave] ?? null;
        }

        return $versoes;
    }

    private static function chaveVersao(string $dominio, int $empresaId): string
    {
        return self::PREFIXO.'v:'.$dominio.':'.$empresaId;
    }

    private static function chaveGlobal(): string
    {
        return self::PREFIXO.'v:global';
    }

    private static function empresaAtual(): int
    {
        try {
            return (int) (auth()->user()?->empresa_id ?? 0);
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Esquece as versões lidas (testes e processos longos que atendem várias requisições).
     */
    public static function esquecerLeituras(): void
    {
        self::$lidas = [];
    }
}
