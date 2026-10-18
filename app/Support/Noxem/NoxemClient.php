<?php

namespace App\Support\Noxem;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * API de streaming da Noxem Cloud (https://api.noxem.cloud/api), autenticada só pela
 * API Key (Bearer sk_live_...). Respostas vêm no envelope { success, data }: devolve o "data".
 * Os bytes do vídeo nunca passam por aqui: o upload vai direto para as URLs pré-assinadas
 * do storage e o player toca pela URL de playback.
 */
class NoxemClient
{
    public static function enabled(): bool
    {
        return config('linkvideo.video_storage') === 'noxem';
    }

    public function contract(): array
    {
        return $this->send('get', '/streaming/contract');
    }

    /** @return array{id: string, status: string} */
    public function createVideo(string $title, int $sizeBytes, ?string $filename, ?string $contentType): array
    {
        return $this->send('post', '/streaming/videos', array_filter([
            'title' => mb_substr($title, 0, 200) ?: 'Vídeo',
            'sizeBytes' => $sizeBytes,
            'filename' => $filename ? mb_substr($filename, 0, 255) : null,
            'contentType' => $contentType,
            'visibility' => 'private',
        ], fn ($value) => $value !== null));
    }

    public function video(string $videoId): array
    {
        return $this->send('get', "/streaming/videos/{$videoId}");
    }

    public function updateVideo(string $videoId, array $fields): array
    {
        return $this->send('patch', "/streaming/videos/{$videoId}", $fields);
    }

    public function deleteVideo(string $videoId): void
    {
        $this->send('delete', "/streaming/videos/{$videoId}");
    }

    /**
     * Abre o multipart. Resposta: session { id, partSize, partCount }, parts[] { partNumber, size, url }, expiresIn.
     */
    public function createUpload(string $videoId, int $sizeBytes): array
    {
        return $this->send('post', "/streaming/videos/{$videoId}/uploads", ['sizeBytes' => $sizeBytes]);
    }

    /** @param  list<int>  $partNumbers  até 100 */
    public function signParts(string $sessionId, array $partNumbers): array
    {
        return $this->send('post', "/streaming/uploads/{$sessionId}/parts", ['partNumbers' => array_values($partNumbers)]);
    }

    public function uploadedParts(string $sessionId): array
    {
        return $this->send('get', "/streaming/uploads/{$sessionId}/parts");
    }

    public function completeUpload(string $sessionId): array
    {
        return $this->send('post', "/streaming/uploads/{$sessionId}/complete");
    }

    public function abortUpload(string $sessionId): void
    {
        $this->send('post', "/streaming/uploads/{$sessionId}/abort");
    }

    /** URL do MP4 na CDN: url, expiresAt, durationSec, thumbnailUrl. */
    public function playback(string $videoId): array
    {
        return $this->send('post', "/streaming/videos/{$videoId}/playback");
    }

    private function request(bool $idempotent): PendingRequest
    {
        $key = config('services.noxem.key');

        if (blank($key)) {
            throw new NoxemException('NOXEM_API_KEY não configurada.');
        }

        $request = Http::baseUrl(config('services.noxem.url'))
            ->withToken($key)
            ->acceptJson()
            ->timeout(config('services.noxem.timeout'));

        // Repete só leituras/remoções (falha de rede ou 5xx). Um POST repetido poderia criar vídeo duplicado.
        return $idempotent
            ? $request->retry(2, 500, fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false)
            : $request;
    }

    private function send(string $method, string $path, array $data = []): array
    {
        $idempotent = in_array($method, ['get', 'delete'], true);

        try {
            $response = $idempotent
                ? $this->request(true)->{$method}($path)
                : $this->request(false)->asJson()->{$method}($path, $data ?: (object) []);
        } catch (ConnectionException $e) {
            throw new NoxemException('Não foi possível falar com a Noxem Cloud. Tente de novo em instantes.', previous: $e);
        }

        if ($response->failed() || $response->json('success') === false) {
            throw NoxemException::fromResponse($response, $method, $path);
        }

        $data = $response->json('data');

        return is_array($data) ? $data : [];
    }
}
