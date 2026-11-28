<?php

/*
|--------------------------------------------------------------------------
| NFS-e Padrão Nacional (Sefin Nacional)
|--------------------------------------------------------------------------
|
| A DPS é assinada e enviada à Sefin Nacional (API REST com certificado A1 em mTLS).
| O leiaute muda com a NT 009: a produção restrita já usa o leiaute de 20/10/2026
| (finNFSe na raiz da DPS, CST e cClassTrib antes do grupo gIBSCBS opcional) e a produção
| ainda usa o de 09/02/2026 até a virada. Quando a produção trocar de leiaute, basta
| ajustar NFSE_LEIAUTE_PRODUCAO no .env e rodar "php artisan optimize".
|
*/

return [
    'ver_aplic' => env('NFSE_VER_APLIC', 'ERP-NFSE-1.0'),

    'timeout' => (int) env('NFSE_TIMEOUT', 30),

    'connect_timeout' => (int) env('NFSE_CONNECT_TIMEOUT', 10),

    // Confere o certificado do servidor da Sefin. Só desligue se a cadeia ICP-Brasil faltar no servidor.
    'verificar_ssl' => (bool) env('NFSE_VERIFICAR_SSL', true),

    'leiaute' => [
        1 => env('NFSE_LEIAUTE_PRODUCAO', '20260209'),
        2 => env('NFSE_LEIAUTE_HOMOLOGACAO', '20261020'),
    ],

    'leiautes' => [
        '20260209' => 'v1.01-20260209',
        '20261020' => 'v1.01-20261020',
    ],

    'sefin' => [
        1 => env('NFSE_SEFIN_PRODUCAO', 'https://sefin.nfse.gov.br/sefinnacional'),
        2 => env('NFSE_SEFIN_HOMOLOGACAO', 'https://sefin.producaorestrita.nfse.gov.br/SefinNacional'),
    ],

    'adn' => [
        1 => env('NFSE_ADN_PRODUCAO', 'https://adn.nfse.gov.br'),
        2 => env('NFSE_ADN_HOMOLOGACAO', 'https://adn.producaorestrita.nfse.gov.br'),
    ],

    'consulta_publica' => 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=',
];
