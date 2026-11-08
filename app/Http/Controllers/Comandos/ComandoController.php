<?php

namespace App\Http\Controllers\Comandos;

use App\Comandos\Assistente;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Assistente: entende a frase, pergunta o que faltar e grava só depois da confirmação. */
class ComandoController extends Controller
{
    public function __construct(private readonly Assistente $assistente) {}

    public function interpretar(Request $request): JsonResponse
    {
        $dados = $request->validate(
            ['texto' => 'required|string|max:300'],
            ['texto.required' => 'Escreva o que você precisa.', 'texto.max' => 'Mensagem longa demais: use até 300 caracteres.'],
        );

        return response()->json($this->assistente->responder($dados['texto'], $request->user())->paraJson());
    }

    /** Botões da prévia: "Confirmar" grava; "Cancelar" (acao=cancelar) descarta sem gravar. */
    public function confirmar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'token' => 'required|string|size:32',
            'acao' => 'nullable|in:confirmar,cancelar',
        ]);

        $resposta = ($dados['acao'] ?? 'confirmar') === 'cancelar'
            ? $this->assistente->cancelar($dados['token'])
            : $this->assistente->confirmar($dados['token'], $request->user());

        return response()->json($resposta->paraJson());
    }

    public function ajuda(Request $request): JsonResponse
    {
        return response()->json($this->assistente->ajuda($request->user())->paraJson());
    }
}
