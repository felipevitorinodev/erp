<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IA opcional do assistente
    |--------------------------------------------------------------------------
    |
    | Desligada por padrão. Quando ligada, só entra se as regras não reconhecerem
    | a frase: escolhe uma rotina já mapeada e reescreve o pedido no formato dela.
    | Quem grava continua sendo a rotina, e só depois da confirmação.
    |
    | O endereço fala a API de chat no formato da OpenAI. O Ollama neste
    | computador não manda a frase para fora. Groq e Gemini têm cota gratuita,
    | mas a frase (que pode ter nome e documento) sai da rede.
    |
    */

    'ia' => [
        'ativa' => filter_var(env('ASSISTENTE_IA', false), FILTER_VALIDATE_BOOLEAN),
        'url' => env('ASSISTENTE_IA_URL', 'http://127.0.0.1:11434/v1'),
        'modelo' => env('ASSISTENTE_IA_MODELO', 'llama3.2'),
        'chave' => env('ASSISTENTE_IA_CHAVE'),
        'timeout' => (int) env('ASSISTENTE_IA_TIMEOUT', 12),
    ],

];
