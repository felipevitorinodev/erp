<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Trilha das ações críticas: quem fez, quando, em qual registro e o que mudou.
 * Grava em storage/logs/auditoria-AAAA-MM-DD.log (canal "auditoria" em config/logging.php).
 * Não registrar CPF, CNPJ, dados bancários, senhas nem textos livres digitados pelo usuário.
 */
class Auditoria
{
    /**
     * @param  array<string, mixed>  $dados  valores relevantes da ação (número, total, antes e depois)
     */
    public static function registrar(string $acao, string $entidade, int|string|null $id, array $dados = []): void
    {
        $usuario = auth()->user();

        try {
            Log::channel('auditoria')->info($acao, [
                'entidade' => $entidade,
                'id' => $id,
                'empresa_id' => $usuario?->empresa_id,
                'usuario_id' => $usuario?->id,
                'usuario' => $usuario?->name,
                'ip' => request()->ip(),
                'dados' => $dados,
            ]);
        } catch (Throwable $e) {
            // A ação já foi concluída; uma falha no arquivo de auditoria não pode desfazê-la nem virar erro na tela.
            error_log('Falha ao gravar auditoria ('.$acao.'): '.$e->getMessage());
        }
    }
}
