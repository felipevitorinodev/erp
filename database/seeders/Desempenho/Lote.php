<?php

namespace Database\Seeders\Desempenho;

use Illuminate\Support\Facades\DB;

/**
 * Inserção em lote numa tabela, com ids definidos aqui (a partir do maior id existente),
 * para ligar registros sem consultar o banco a cada linha.
 */
final class Lote
{
    /** Máximo de valores por comando: cabe no limite do SQLite (32.766) e do MySQL (65.535). */
    private const VALORES_POR_COMANDO = 30000;

    /** @var list<array<string, mixed>> */
    private array $linhas = [];

    private int $proximoId;

    private ?int $tamanho = null;

    public int $total = 0;

    public function __construct(public readonly string $tabela)
    {
        $this->proximoId = (int) DB::table($tabela)->max('id') + 1;
    }

    /** Depois de inserções feitas por fora (Consumidor, administrador), continua acima do maior id gravado. */
    public function sincronizar(): void
    {
        $this->gravar();
        $this->proximoId = max($this->proximoId, (int) DB::table($this->tabela)->max('id') + 1);
    }

    public function reservarId(): int
    {
        return $this->proximoId++;
    }

    /** @param  array<string, mixed>  $linha  sempre com as mesmas colunas, na mesma ordem */
    public function add(array $linha): void
    {
        $this->linhas[] = $linha;
        $this->tamanho ??= max(1, intdiv(self::VALORES_POR_COMANDO, count($linha)));

        if (count($this->linhas) >= $this->tamanho) {
            $this->gravar();
        }
    }

    public function gravar(): void
    {
        if ($this->linhas === []) {
            return;
        }

        DB::table($this->tabela)->insert($this->linhas);
        $this->total += count($this->linhas);
        $this->linhas = [];
    }
}
