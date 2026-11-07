<?php

/*
 * Roteador do `php artisan serve` (só desenvolvimento; Apache/Nginx não usam este arquivo).
 *
 * O servidor embutido do PHP entrega arquivos estáticos sem Cache-Control, ETag nem Last-Modified,
 * então o navegador baixava fontes, CSS e JS de novo a cada tela. Aqui os estáticos saem com cache:
 * fontes e arquivos versionados (?v=) por 1 ano; o restante revalida via ETag (resposta 304).
 */

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    $arquivo = realpath($publicPath.$uri);
    $raiz = realpath($publicPath);

    if ($arquivo === false || ! is_file($arquivo) || ! str_starts_with($arquivo, $raiz.DIRECTORY_SEPARATOR)) {
        return false;
    }

    $tipos = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'text/javascript; charset=UTF-8',
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
    ];

    $extensao = strtolower(pathinfo($arquivo, PATHINFO_EXTENSION));

    if (! isset($tipos[$extensao])) {
        return false;
    }

    $versionado = isset($_GET['v']) && $_GET['v'] !== '';

    $cache = match (true) {
        in_array($extensao, ['woff2', 'woff', 'ttf'], true) => 'public, max-age=31536000, immutable',
        $versionado => 'public, max-age=31536000, immutable',
        in_array($extensao, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico'], true) => 'public, max-age=86400',
        default => 'no-cache',
    };

    $modificado = filemtime($arquivo);
    $tamanho = filesize($arquivo);
    $etag = '"'.dechex($modificado).'-'.dechex($tamanho).'"';

    header('Content-Type: '.$tipos[$extensao]);
    header('Cache-Control: '.$cache);
    header('ETag: '.$etag);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $modificado).' GMT');

    if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);

        return true;
    }

    header('Content-Length: '.$tamanho);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') {
        readfile($arquivo);
    }

    return true;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
