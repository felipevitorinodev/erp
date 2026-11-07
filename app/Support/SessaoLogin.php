<?php

namespace App\Support;

use Illuminate\Http\RedirectResponse;

class SessaoLogin
{
    public const MARCADOR = 'visys_sessao';

    public static function marcar(): void
    {
        cookie()->queue(cookie(
            self::MARCADOR,
            '1',
            0,
            config('session.path'),
            config('session.domain'),
            config('session.secure'),
            true,
            false,
            config('session.same_site', 'lax')
        ));
    }

    public static function esquecer(): void
    {
        cookie()->queue(cookie()->forget(
            self::MARCADOR,
            config('session.path'),
            config('session.domain')
        ));
    }

    public static function redirecionar(): RedirectResponse
    {
        $destino = session()->pull('url.intended', '/');

        if (! is_string($destino) || ! self::interno($destino)) {
            $destino = '/';
        }

        return redirect()->to($destino);
    }

    public static function interno(string $destino): bool
    {
        $destino = trim($destino);

        if ($destino === '' || preg_match('/[\s\\\\]/', $destino)) {
            return false;
        }

        if (str_starts_with($destino, '/') && ! str_starts_with($destino, '//')) {
            return true;
        }

        $partes = parse_url($destino);
        if (! is_array($partes) || empty($partes['host']) || isset($partes['user'])) {
            return false;
        }

        $esquema = strtolower((string) ($partes['scheme'] ?? ''));
        if (! in_array($esquema, ['http', 'https'], true)) {
            return false;
        }

        if (strcasecmp((string) $partes['host'], request()->getHost()) !== 0) {
            return false;
        }

        $porta = $partes['port'] ?? ($esquema === 'https' ? 443 : 80);

        return (int) $porta === (int) request()->getPort();
    }
}
