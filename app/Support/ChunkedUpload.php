<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Upload de vídeos em partes: o navegador envia o arquivo em pedaços pequenos,
 * que são concatenados em storage/app/private/uploads-tmp. Assim o tamanho do
 * vídeo não fica limitado pelo upload_max_filesize / post_max_size do servidor.
 */
class ChunkedUpload
{
    private const TMP_DIR = 'uploads-tmp';

    private const STALE_AFTER_SECONDS = 86400;

    private Filesystem $disk;

    public function __construct()
    {
        $this->disk = Storage::disk('local');
    }

    /** Tamanho de cada parte, respeitando os limites do PHP do servidor. */
    public static function chunkSize(): int
    {
        $desired = config('linkvideo.uploads.chunk_mb') * 1024 * 1024;
        $serverLimit = min(
            Bytes::fromIni((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX,
            Bytes::fromIni((string) ini_get('post_max_size')) ?: PHP_INT_MAX,
        );

        // Folga para os demais campos do POST multipart.
        return max(256 * 1024, min($desired, $serverLimit - 256 * 1024));
    }

    public static function maxBytes(): int
    {
        return config('linkvideo.uploads.video_max_mb') * 1024 * 1024;
    }

    /**
     * Recebe uma parte. Retorna o estado do upload após anexá-la.
     *
     * @return array{done: bool, received: int, token?: string}
     */
    public function receive(string $uploadId, int $index, int $total, string $filename, int $filesize, UploadedFile $chunk, int $userId): array
    {
        $meta = $this->meta($uploadId);

        if ($index === 0 && $meta === null) {
            $this->cleanupStale();
            $meta = $this->start($uploadId, $total, $filename, $filesize, $userId);
        }

        if ($meta === null || $meta['user_id'] !== $userId || $meta['total'] !== $total) {
            throw ValidationException::withMessages(['chunk' => 'Upload não encontrado. Selecione o vídeo novamente.']);
        }

        if ($meta['complete'] || $index < $meta['received']) {
            // Parte repetida (reenvio após falha de rede): nada a fazer.
            return $this->status($uploadId, $meta);
        }

        if ($index !== $meta['received']) {
            throw ValidationException::withMessages(['chunk' => 'Parte fora de ordem. Reinicie o envio.']);
        }

        $partPath = $this->disk->path($this->partFile($uploadId));
        $out = fopen($partPath, 'ab');
        $in = fopen($chunk->getRealPath(), 'rb');
        stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);
        clearstatcache(true, $partPath);

        if (filesize($partPath) > $meta['size']) {
            $this->discard($uploadId);
            throw ValidationException::withMessages(['chunk' => 'O arquivo recebido é maior que o informado.']);
        }

        $meta['received']++;

        if ($meta['received'] === $meta['total']) {
            $meta = $this->finish($uploadId, $meta, $partPath);
        }

        $this->saveMeta($uploadId, $meta);

        return $this->status($uploadId, $meta);
    }

    /**
     * Move um upload concluído para a pasta definitiva de vídeos.
     *
     * @return array{path: string, mime: string, size: int, original_name: string}
     */
    public function claim(string $uploadId, int $userId): array
    {
        $meta = Str::isUuid($uploadId) ? $this->meta($uploadId) : null;

        if ($meta === null || ! $meta['complete'] || $meta['user_id'] !== $userId) {
            throw ValidationException::withMessages(['video_token' => 'O envio do vídeo não foi concluído. Selecione o arquivo novamente.']);
        }

        $path = 'videos/'.Str::uuid().'.'.$meta['extension'];
        $this->disk->makeDirectory('videos');
        $this->disk->move($this->partFile($uploadId), $path);
        $this->disk->delete($this->metaFile($uploadId));

        return [
            'path' => $path,
            'mime' => $meta['mime'],
            'size' => $meta['size'],
            'original_name' => $meta['name'],
        ];
    }

    private function start(string $uploadId, int $total, string $filename, int $filesize, int $userId): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, config('linkvideo.uploads.video_extensions'), true)) {
            throw ValidationException::withMessages(['chunk' => 'Formato não suportado. Envie MP4, M4V, WEBM, MOV ou OGV.']);
        }

        if ($filesize > self::maxBytes()) {
            throw ValidationException::withMessages(['chunk' => 'O vídeo excede o limite de '.Bytes::human(self::maxBytes()).'.']);
        }

        if ($total !== (int) ceil($filesize / self::chunkSize())) {
            throw ValidationException::withMessages(['chunk' => 'Quantidade de partes inválida. Recarregue a página.']);
        }

        $this->disk->makeDirectory(self::TMP_DIR);
        $this->disk->put($this->partFile($uploadId), '');

        $meta = [
            'user_id' => $userId,
            'name' => Str::limit(basename($filename), 250, ''),
            'extension' => $extension,
            'size' => $filesize,
            'total' => $total,
            'received' => 0,
            'complete' => false,
            'mime' => null,
        ];
        $this->saveMeta($uploadId, $meta);

        return $meta;
    }

    private function finish(string $uploadId, array $meta, string $partPath): array
    {
        if (filesize($partPath) !== $meta['size']) {
            $this->discard($uploadId);
            throw ValidationException::withMessages(['chunk' => 'O arquivo chegou incompleto. Tente enviar novamente.']);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($partPath) ?: 'application/octet-stream';

        if (! in_array($mime, config('linkvideo.uploads.video_mimes'), true)) {
            $this->discard($uploadId);
            throw ValidationException::withMessages(['chunk' => 'O arquivo enviado não parece ser um vídeo válido.']);
        }

        // Navegadores tocam .mov (H.264) e arquivos sem mime detectado melhor como mp4.
        if (in_array($mime, ['video/quicktime', 'application/octet-stream'], true)) {
            $mime = $meta['extension'] === 'webm' ? 'video/webm' : 'video/mp4';
        }

        $meta['complete'] = true;
        $meta['mime'] = $mime;

        return $meta;
    }

    private function status(string $uploadId, array $meta): array
    {
        return array_filter([
            'done' => $meta['complete'],
            'received' => $meta['received'],
            'token' => $meta['complete'] ? $uploadId : null,
        ], fn ($value) => $value !== null);
    }

    private function meta(string $uploadId): ?array
    {
        $file = $this->metaFile($uploadId);

        return $this->disk->exists($file) ? json_decode($this->disk->get($file), true) : null;
    }

    private function saveMeta(string $uploadId, array $meta): void
    {
        $this->disk->put($this->metaFile($uploadId), json_encode($meta));
    }

    private function discard(string $uploadId): void
    {
        $this->disk->delete([$this->partFile($uploadId), $this->metaFile($uploadId)]);
    }

    /** Remove envios abandonados há mais de 24h. */
    private function cleanupStale(): void
    {
        foreach ($this->disk->files(self::TMP_DIR) as $file) {
            if ($this->disk->lastModified($file) < time() - self::STALE_AFTER_SECONDS) {
                $this->disk->delete($file);
            }
        }
    }

    private function partFile(string $uploadId): string
    {
        return self::TMP_DIR.'/'.$uploadId.'.part';
    }

    private function metaFile(string $uploadId): string
    {
        return self::TMP_DIR.'/'.$uploadId.'.json';
    }
}
