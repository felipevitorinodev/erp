<?php

namespace App\Models\Nfses;

use App\Models\Empresas\Empresa;
use App\Models\Vendas\Venda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * NFS-e Padrão Nacional. O número próprio é o da DPS (série + nDPS); o número da NFS-e e a chave de acesso
 * (50 posições) vêm da Sefin Nacional na autorização.
 */
class Nfse extends Model
{
    use SoftDeletes;

    /** Motivos do evento de cancelamento e101101 (TSCodJustCanc). */
    public const MOTIVOS_CANCELAMENTO = [
        '1' => 'Erro na emissão',
        '2' => 'Serviço não prestado',
        '9' => 'Outros',
    ];

    protected $table = 'nfses';

    protected $fillable = [
        'empresa_id',
        'venda_id',
        'serie',
        'numero',
        'id_dps',
        'numero_nfse',
        'chave_acesso',
        'situacao',
        'ambiente',
        'leiaute',
        'c_trib_nac',
        'c_trib_mun',
        'nbs',
        'cst_ibs_cbs',
        'class_trib',
        'ind_op',
        'municipio_prestacao',
        'descricao',
        'valor_servico',
        'valor_desconto',
        'valor_liquido',
        'iss_retido',
        'aliquota_iss',
        'valor_iss',
        'data_competencia',
        'data_emissao',
        'xml_assinado',
        'xml_nfse',
        'xml_cancelamento',
        'codigo_status',
        'motivo_status',
        'mensagens',
        'data_autorizacao',
        'data_cancelamento',
        'motivo_cancelamento',
    ];

    protected $casts = [
        'numero' => 'integer',
        'ambiente' => 'integer',
        'valor_servico' => 'decimal:2',
        'valor_desconto' => 'decimal:2',
        'valor_liquido' => 'decimal:2',
        'iss_retido' => 'boolean',
        'aliquota_iss' => 'decimal:2',
        'valor_iss' => 'decimal:2',
        'data_competencia' => 'date',
        'data_emissao' => 'datetime',
        'data_autorizacao' => 'datetime',
        'data_cancelamento' => 'datetime',
        'mensagens' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function venda(): BelongsTo
    {
        return $this->belongsTo(Venda::class);
    }

    public function podeCancelar(): bool
    {
        return $this->situacao === 'autorizada' && filled($this->chave_acesso);
    }

    /** Número exibido: o da NFS-e quando autorizada, senão o da DPS. */
    public function numeroExibicao(): string
    {
        return filled($this->numero_nfse) ? (string) $this->numero_nfse : 'DPS '.$this->serie.'/'.$this->numero;
    }
}
