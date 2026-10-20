<?php

namespace App\Models\OrdensServico;

use App\Models\Clientes\Cliente;
use App\Models\Empresas\Empresa;
use App\Models\Funcionarios\Funcionario;
use App\Models\Orcamentos\Orcamento;
use App\Models\Usuarios\User;
use App\Models\Vendas\Venda;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrdemServico extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SITUACOES = [
        'aberta' => 'Aberta',
        'em_execucao' => 'Em execução',
        'aguardando_peca' => 'Aguardando peça',
        'concluida' => 'Concluída',
        'faturada' => 'Faturada',
        'cancelada' => 'Cancelada',
    ];

    /** Situações em que a OS ainda pode ser editada, ter a situação trocada ou ser faturada. */
    public const SITUACOES_ABERTAS = ['aberta', 'em_execucao', 'aguardando_peca', 'concluida'];

    protected $table = 'ordens_servico';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'usuario_id',
        'funcionario_id',
        'orcamento_id',
        'venda_id',
        'numero',
        'data_abertura',
        'data_previsao',
        'data_conclusao',
        'equipamento',
        'marca',
        'modelo',
        'numero_serie',
        'acessorios',
        'defeito_relatado',
        'diagnostico',
        'servico_executado',
        'garantia',
        'subtotal',
        'desconto',
        'acrescimo',
        'total',
        'forma_pagamento',
        'observacoes',
        'situacao',
    ];

    protected $casts = [
        'data_abertura'  => 'date',
        'data_previsao'  => 'date',
        'data_conclusao' => 'date',
        'subtotal'       => 'decimal:2',
        'desconto'       => 'decimal:2',
        'acrescimo'      => 'decimal:2',
        'total'          => 'decimal:2',
    ];

    public function estaAberta(): bool
    {
        return in_array($this->situacao, self::SITUACOES_ABERTAS, true);
    }

    public function situacaoLabel(): string
    {
        return self::SITUACOES[$this->situacao] ?? (string) $this->situacao;
    }

    public function situacaoBadge(): string
    {
        return match ($this->situacao) {
            'concluida', 'faturada' => 'badge--success',
            'cancelada' => 'badge--error',
            'aguardando_peca' => 'badge--warning',
            'em_execucao' => 'badge--info',
            default => 'badge--neutral',
        };
    }

    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Funcionario::class, 'funcionario_id');
    }

    public function orcamento()
    {
        return $this->belongsTo(Orcamento::class);
    }

    public function venda()
    {
        return $this->belongsTo(Venda::class);
    }

    public function itens()
    {
        return $this->hasMany(OrdemServicoItem::class);
    }
}
