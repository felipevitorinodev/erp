<?php

namespace App\Comandos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** Resultado de uma busca por nome: um registro só, vários parecidos ou nenhum (com sugestões de grafia). */
final class Achado
{
    /**
     * @param  Collection<int, Model>  $opcoes  o que bateu com o termo
     * @param  Collection<int, Model>  $sugestoes  nomes parecidos, quando nada bateu (erro de digitação)
     */
    private function __construct(
        public readonly string $termo,
        public readonly ?Model $registro,
        public readonly Collection $opcoes,
        public readonly Collection $sugestoes,
    ) {}

    public static function unico(string $termo, Model $registro): self
    {
        return new self($termo, $registro, collect([$registro]), collect());
    }

    /**
     * @param  Collection<int, Model>  $opcoes
     * @param  Collection<int, Model>|null  $sugestoes
     */
    public static function opcoes(string $termo, Collection $opcoes, ?Collection $sugestoes = null): self
    {
        return $opcoes->count() === 1
            ? self::unico($termo, $opcoes->first())
            : new self($termo, null, $opcoes, $sugestoes ?? collect());
    }

    public function encontrou(): bool
    {
        return $this->registro !== null;
    }

    public function nenhum(): bool
    {
        return $this->registro === null && $this->opcoes->isEmpty();
    }
}
