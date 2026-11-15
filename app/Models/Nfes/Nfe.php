<?php

namespace App\Models\Nfes;

use App\Models\Empresas\Empresa;
use App\Models\Vendas\Venda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nfe extends Model
{
    use SoftDeletes;

    public const MODELO_NFE = 55;

    public const MODELO_NFCE = 65;

    /** Prazo de cancelamento da NFC-e (em minutos) nas UFs que fixam um prazo menor que 24 horas. */
    public const PRAZO_CANCELAMENTO_NFCE_MINUTOS = ['PB' => 30];

    /** Carta de Correção: até 20 por NF-e (MOC, regra GA03, rejeição 594). Só modelo 55 (GA03a, rejeição 784). */
    public const CCE_MAXIMO = 20;

    protected $table = 'nfes';

    protected $fillable = [
        'empresa_id',
        'venda_id',
        'modelo',
        'numero',
        'serie',
        'chave_acesso',
        'situacao',
        'ambiente',
        'xml_assinado',
        'xml_autorizado',
        'xml_cancelamento',
        'protocolo',
        'recibo',
        'codigo_status',
        'motivo_status',
        'data_autorizacao',
        'data_cancelamento',
    ];

    protected $casts = [
        'modelo' => 'integer',
        'ambiente' => 'integer',
        'data_autorizacao' => 'datetime',
        'data_cancelamento' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function cartasCorrecao(): HasMany
    {
        return $this->hasMany(NfeCartaCorrecao::class)->orderBy('sequencia');
    }

    /** CC-e só vale para NF-e (modelo 55) autorizada: a SEFAZ recusa NFC-e (784) e nota cancelada ou denegada (580). */
    public function aceitaCartaCorrecao(): bool
    {
        return (int) $this->modelo === self::MODELO_NFE
            && $this->situacao === 'autorizada'
            && filled($this->chave_acesso);
    }

    public function podeCancelar(): bool
    {
        if ($this->situacao !== 'autorizada' || $this->data_autorizacao === null) {
            return false;
        }

        return $this->data_autorizacao->gt(now()->subMinutes($this->prazoCancelamentoMinutos()));
    }

    /**
     * NF-e: 24 horas (MOC, evento 110111, rejeição 501). NFC-e: prazo da UF; na Paraíba, 30 minutos
     * (Portaria GSER nº 17/2018, art. 6º, II, redação da Portaria SEFAZ nº 128/2021). Depois disso a SEFAZ rejeita (501).
     */
    public function prazoCancelamentoMinutos(): int
    {
        if ((int) $this->modelo !== self::MODELO_NFCE) {
            return 24 * 60;
        }

        return self::PRAZO_CANCELAMENTO_NFCE_MINUTOS[$this->ufDaEmpresa()] ?? 24 * 60;
    }

    /** Texto do prazo para a tela: "30 minutos" ou "24 horas". */
    public function prazoCancelamentoTexto(): string
    {
        $minutos = $this->prazoCancelamentoMinutos();

        return $minutos % 60 === 0 ? ($minutos / 60).' horas' : $minutos.' minutos';
    }

    private function ufDaEmpresa(): string
    {
        $empresa = $this->relationLoaded('empresa') ? $this->empresa : null;

        if ($empresa === null) {
            $atual = Empresa::atual();
            $empresa = $atual && (int) $atual->id === (int) $this->empresa_id ? $atual : $this->empresa;
        }

        return strtoupper(trim((string) $empresa?->uf));
    }

    /** Motivo da SEFAZ sem o prefixo "Rejeicao:" (a tela já diz que foi rejeitada). */
    public function motivoParaTela(): string
    {
        return trim((string) preg_replace('/^\s*rejei[cç][aã]o\s*:\s*/iu', '', (string) $this->motivo_status));
    }
}
