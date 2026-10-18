<?php

namespace App\Support\Noxem;

use App\Models\Video;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reprodução dos vídeos guardados na Noxem Cloud: acompanha o preparo (uploading →
 * processing → ready | failed) e obtém a URL do MP4 na CDN, guardada em cache enquanto
 * ainda tem bastante validade (todos que abrirem o filme nesse intervalo usam a mesma).
 */
class NoxemPlayback
{
    /** Intervalo mínimo entre duas consultas de status do mesmo vídeo. */
    private const STATUS_EVERY_SECONDS = 15;

    /** Máximo de vídeos com status consultado numa mesma página. */
    private const REFRESH_LIMIT = 10;

    public function __construct(private NoxemClient $client) {}

    /** URL de reprodução (null enquanto o vídeo não fica pronto). */
    public function url(Video $video): ?string
    {
        if (! $video->noxem_video_id || ! $this->refresh($video)->isNoxemReady()) {
            return null;
        }

        $key = "noxem-playback:{$video->noxem_video_id}";

        if ($url = Cache::get($key)) {
            return $url;
        }

        $playback = $this->client->playback($video->noxem_video_id);
        $url = (string) $playback['url'];

        // Guarda por metade da validade restante: quem recebe a URL sempre tem tempo de tocar.
        $expiresAt = isset($playback['expiresAt']) ? CarbonImmutable::parse($playback['expiresAt']) : now()->addMinutes(15);
        $seconds = (int) floor(now()->diffInSeconds($expiresAt, false) / 2);
        if ($seconds > 30) {
            Cache::put($key, $url, $seconds);
        }

        if (! $video->duration && isset($playback['durationSec'])) {
            $video->forceFill(['duration' => (int) round($playback['durationSec'])])->saveQuietly();
        }

        return $url;
    }

    /** Consulta o status na Noxem (no máximo a cada 15 s por vídeo) e grava no registro. */
    public function refresh(Video $video): Video
    {
        if (! $video->noxem_video_id || $video->isNoxemReady() || $video->noxem_status === 'failed') {
            return $video;
        }

        if (! Cache::add("noxem-status:{$video->noxem_video_id}", true, self::STATUS_EVERY_SECONDS)) {
            return $video;
        }

        try {
            $remote = $this->client->video($video->noxem_video_id);
        } catch (NoxemException $e) {
            Log::warning("Noxem: não foi possível consultar o vídeo {$video->id}: {$e->getMessage()}");

            return $video;
        }

        $status = in_array($remote['status'] ?? null, ['uploading', 'processing', 'ready', 'failed'], true)
            ? $remote['status']
            : $video->noxem_status;

        $video->forceFill([
            'noxem_status' => $status,
            'noxem_error' => $status === 'failed' ? mb_substr((string) ($remote['failureReason'] ?? 'Falha no preparo do vídeo.'), 0, 500) : null,
        ])->saveQuietly();

        return $video;
    }

    /** Atualiza o status dos vídeos ainda em preparo de uma lista (telas do painel). */
    public function refreshMany(iterable $videos): void
    {
        $pending = collect($videos)
            ->filter(fn (Video $video) => $video->noxem_video_id && in_array($video->noxem_status, ['uploading', 'processing'], true))
            ->take(self::REFRESH_LIMIT);

        foreach ($pending as $video) {
            $this->refresh($video);
        }
    }

    /** Apaga o vídeo na Noxem sem travar a exclusão local se a API falhar. */
    public function forget(?string $noxemVideoId): void
    {
        if (! $noxemVideoId) {
            return;
        }

        Cache::forget("noxem-playback:{$noxemVideoId}");

        try {
            $this->client->deleteVideo($noxemVideoId);
        } catch (NoxemException $e) {
            if ($e->status !== 404) {
                report($e);
            }
        }
    }
}
