<?php

namespace App\Models\FormasPagamento;

use App\Models\Empresas\Empresa;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormaPagamento extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'formas_pagamento';

    protected $fillable = [
        'empresa_id',
        'nome',
        'tipo',
        'gera_conta_receber',
        'dias_vencimento',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'gera_conta_receber' => 'boolean',
        'dias_vencimento' => 'integer',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function isPrazo(): bool
    {
        return $this->tipo === 'prazo' || $this->gera_conta_receber;
    }

    public function isAvista(): bool
    {
        return ! $this->isPrazo();
    }

    public function ehCartao(): bool
    {
        $nome = mb_strtolower((string) $this->nome);
        $nome = strtr($nome, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u',
            'ç' => 'c',
        ]);

        return str_contains($nome, 'credito')
            || str_contains($nome, 'debito')
            || str_contains($nome, 'cartao');
    }

    public function permiteParcelar(): bool
    {
        return $this->isPrazo() || $this->ehCartao();
    }

    /**
     * Pagamento integral (à vista): quita o valor total na venda.
     */
    public static function pagamentoIntegral(?string $nome, int $empresaId): bool
    {
        $forma = static::buscarPorNome($nome, $empresaId);

        return $forma !== null && $forma->isAvista();
    }

    public static function buscarPorNome(?string $nome, int $empresaId): ?self
    {
        $nome = trim((string) $nome);

        if ($nome === '') {
            return null;
        }

        return static::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->whereRaw('LOWER(nome) = ?', [mb_strtolower($nome)])
            ->first();
    }
}
