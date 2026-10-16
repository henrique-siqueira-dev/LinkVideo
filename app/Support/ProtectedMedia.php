<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Entrega de vídeo e capa nas telas públicas (link do cliente e link fixo do filme):
 * - o endereço do arquivo é assinado, expira e fica preso ao IP de quem abriu a página;
 * - abrir o endereço direto numa aba (para baixar) é recusado;
 * - o arquivo sai "inline", nunca como download.
 */
class ProtectedMedia
{
    private const HOURS = 12;

    /** Destinos aceitos no cabeçalho Sec-Fetch-Dest (player, imagem, fetch). "document" = aba. */
    private const ALLOWED_DEST = ['video', 'audio', 'image', 'empty'];

    public static function signedUrl(string $route, array $parameters, Request $request): string
    {
        $relative = URL::temporarySignedRoute(
            $route,
            // Validade fechada na hora cheia: o endereço não muda a cada abertura e a capa fica em cache.
            now()->addHours(self::HOURS)->startOfHour(),
            $parameters + ['k' => static::ipKey($request)],
            absolute: false,
        );

        // A assinatura relativa ignora a subpasta; a URL entregue ao navegador precisa dela.
        return rtrim(url('/'), '/').$relative;
    }

    public static function hasValidSignature(Request $request): bool
    {
        return $request->hasValidRelativeSignature()
            && hash_equals(static::ipKey($request), (string) $request->query('k'));
    }

    public static function blockDirectOpen(Request $request): void
    {
        $dest = $request->header('Sec-Fetch-Dest');
        abort_if($dest !== null && ! in_array($dest, self::ALLOWED_DEST, true), 403);
    }

    public static function file(?string $relativePath, array $headers = []): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($relativePath && $disk->exists($relativePath), 404);

        return response()->file($disk->path($relativePath), $headers + ['X-Robots-Tag' => 'noindex']);
    }

    private static function ipKey(Request $request): string
    {
        return substr(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 0, 16);
    }
}
