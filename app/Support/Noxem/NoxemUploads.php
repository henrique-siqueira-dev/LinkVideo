<?php

namespace App\Support\Noxem;

use App\Support\Bytes;
use App\Support\ChunkedUpload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Upload de vídeo para a Noxem Cloud. A API Key fica só no servidor:
 * 1. start(): cria o vídeo e o multipart na Noxem e devolve ao navegador o plano de partes
 *    com as URLs pré-assinadas;
 * 2. o navegador faz PUT de cada fatia do arquivo direto no storage (não passa por aqui);
 * 3. complete(): fecha o multipart; o token devolvido vai no formulário (video_token);
 * 4. claim(): ao salvar o formulário, troca o token pelo id do vídeo na Noxem.
 * O estado fica no cache, preso ao usuário que começou o envio.
 */
class NoxemUploads
{
    private const TTL_SECONDS = 86400;

    public function __construct(private NoxemClient $client) {}

    /**
     * @return array{token: string, partSize: int, partCount: int, parts: array, expiresIn: int}
     */
    public function start(int $userId, string $filename, int $filesize, ?string $contentType): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, config('linkvideo.uploads.video_extensions'), true)) {
            throw ValidationException::withMessages(['chunk' => 'Formato não suportado. Envie MP4, M4V, WEBM, MOV ou OGV.']);
        }

        if ($filesize > ChunkedUpload::maxBytes()) {
            throw ValidationException::withMessages(['chunk' => 'O vídeo excede o limite de '.Bytes::human(ChunkedUpload::maxBytes()).'.']);
        }

        $name = Str::limit(basename($filename), 250, '');
        $mime = $this->mime($extension, $contentType);

        $video = $this->call(fn () => $this->client->createVideo(pathinfo($name, PATHINFO_FILENAME), $filesize, $name, $mime));

        try {
            $upload = $this->call(fn () => $this->client->createUpload($video['id'], $filesize));
        } catch (ValidationException $e) {
            $this->forgetRemote($video['id']);
            throw $e;
        }

        $token = (string) Str::uuid();
        $this->put($token, [
            'user_id' => $userId,
            'video_id' => $video['id'],
            'session_id' => $upload['session']['id'],
            'part_count' => (int) $upload['session']['partCount'],
            'name' => $name,
            'mime' => $mime,
            'size' => $filesize,
            'complete' => false,
        ]);

        return [
            'token' => $token,
            'partSize' => (int) $upload['session']['partSize'],
            'partCount' => (int) $upload['session']['partCount'],
            'parts' => $this->parts($upload['parts'] ?? []),
            'expiresIn' => (int) ($upload['expiresIn'] ?? 7200),
        ];
    }

    /** Novas URLs para partes que faltam ou cujas URLs venceram (lote de até 100). */
    public function sign(string $token, int $userId, array $partNumbers): array
    {
        $meta = $this->open($token, $userId);
        $numbers = collect($partNumbers)->map(fn ($n) => (int) $n)
            ->filter(fn ($n) => $n >= 1 && $n <= $meta['part_count'])->unique()->take(100)->values()->all();

        if (! $numbers) {
            throw ValidationException::withMessages(['chunk' => 'Partes inválidas. Reinicie o envio.']);
        }

        $signed = $this->call(fn () => $this->client->signParts($meta['session_id'], $numbers));

        return ['parts' => $this->parts($signed['parts'] ?? []), 'expiresIn' => (int) ($signed['expiresIn'] ?? 7200)];
    }

    /** Fecha o multipart (a Noxem confere os tamanhos e começa a preparar o vídeo). */
    public function complete(string $token, int $userId): array
    {
        $meta = $this->open($token, $userId);
        $this->call(fn () => $this->client->completeUpload($meta['session_id']));

        $meta['complete'] = true;
        $this->put($token, $meta);

        return ['done' => true, 'token' => $token];
    }

    /** Envio cancelado no navegador: aborta o multipart e apaga o vídeo vazio. */
    public function abort(string $token, int $userId): void
    {
        $meta = $this->meta($token);

        if (! $meta || $meta['user_id'] !== $userId || $meta['complete']) {
            return;
        }

        Cache::forget($this->key($token));

        try {
            $this->client->abortUpload($meta['session_id']);
        } catch (NoxemException) {
            // Sessão já fechada/vencida: segue para apagar o vídeo.
        }
        $this->forgetRemote($meta['video_id']);
    }

    /**
     * Troca o token do envio concluído pelos dados do vídeo (usado ao salvar o formulário).
     *
     * @return array{video_id: string, mime: string, size: int, original_name: string}
     */
    public function claim(string $token, int $userId): array
    {
        $meta = Str::isUuid($token) ? $this->meta($token) : null;

        if (! $meta || ! $meta['complete'] || $meta['user_id'] !== $userId) {
            throw ValidationException::withMessages(['video_token' => 'O envio do vídeo não foi concluído. Selecione o arquivo novamente.']);
        }

        Cache::forget($this->key($token));

        return [
            'video_id' => $meta['video_id'],
            // A Noxem prepara sempre um MP4 (faststart) para reprodução, seja qual for o original.
            'mime' => 'video/mp4',
            'size' => $meta['size'],
            'original_name' => $meta['name'],
        ];
    }

    private function open(string $token, int $userId): array
    {
        $meta = Str::isUuid($token) ? $this->meta($token) : null;

        if (! $meta || $meta['user_id'] !== $userId || $meta['complete']) {
            throw ValidationException::withMessages(['chunk' => 'Envio não encontrado. Selecione o vídeo novamente.']);
        }

        return $meta;
    }

    /** Erro da Noxem vira erro de validação: o navegador mostra a mensagem no lugar do envio. */
    private function call(callable $callback): array
    {
        try {
            return $callback();
        } catch (NoxemException $e) {
            throw ValidationException::withMessages(['chunk' => $e->getMessage()]);
        }
    }

    private function forgetRemote(string $videoId): void
    {
        try {
            $this->client->deleteVideo($videoId);
        } catch (NoxemException) {
            // Fica um registro vazio na Noxem; não impede o usuário de tentar de novo.
        }
    }

    /** Só o que o navegador precisa de cada parte. */
    private function parts(array $parts): array
    {
        return array_map(fn (array $part) => [
            'partNumber' => (int) $part['partNumber'],
            'size' => (int) $part['size'],
            'url' => (string) $part['url'],
        ], $parts);
    }

    private function mime(string $extension, ?string $contentType): string
    {
        if ($contentType && preg_match('#^video/[\w.+-]{1,100}$#', $contentType)) {
            return $contentType;
        }

        return match ($extension) {
            'webm' => 'video/webm',
            'ogv' => 'video/ogg',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };
    }

    private function meta(string $token): ?array
    {
        return Cache::get($this->key($token));
    }

    private function put(string $token, array $meta): void
    {
        Cache::put($this->key($token), $meta, self::TTL_SECONDS);
    }

    private function key(string $token): string
    {
        return 'noxem-upload:'.$token;
    }
}
