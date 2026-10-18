<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Support\Bytes;
use App\Support\Noxem\NoxemClient;
use App\Support\Noxem\NoxemException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Envia para a Noxem Cloud os vídeos que estão no disco do servidor.
 * O arquivo local continua tocando até a cópia da Noxem ficar pronta; depois disso
 * o player passa a usar a CDN sozinho. Para liberar o disco: --remover-locais.
 */
class NoxemMigrarCommand extends Command
{
    protected $signature = 'linkvideo:noxem-migrar
        {--id=* : Só estes vídeos (id do painel)}
        {--remover-locais : Apaga o arquivo local dos vídeos que já estão prontos na Noxem}';

    protected $description = 'Envia os vídeos locais para a Noxem Cloud (ou apaga os locais já migrados)';

    public function handle(NoxemClient $client): int
    {
        if (blank(config('services.noxem.key'))) {
            $this->error('Defina NOXEM_API_KEY antes de migrar.');

            return self::FAILURE;
        }

        return $this->option('remover-locais') ? $this->removeLocal($client) : $this->migrate($client);
    }

    private function migrate(NoxemClient $client): int
    {
        $videos = $this->query()->whereNull('noxem_video_id')->whereNotNull('video_path')->get();
        $disk = Storage::disk('local');
        $failures = 0;

        if ($videos->isEmpty()) {
            $this->info('Nenhum vídeo local para enviar.');

            return self::SUCCESS;
        }

        foreach ($videos as $video) {
            if (! $disk->exists($video->video_path)) {
                $this->warn("#{$video->id} {$video->title}: arquivo não encontrado, pulando.");
                $failures++;

                continue;
            }

            $path = $disk->path($video->video_path);
            $size = filesize($path);
            $this->line("#{$video->id} {$video->title} (".Bytes::human($size).')');

            try {
                $noxemId = $this->upload($client, $video, $path, $size);
                $video->forceFill(['noxem_video_id' => $noxemId, 'noxem_status' => 'processing', 'noxem_error' => null])->saveQuietly();
                $this->info('  enviado; a Noxem está preparando o vídeo.');
            } catch (Throwable $e) {
                $failures++;
                $this->error("  falhou: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->line('Quando todos estiverem "Noxem Cloud" na lista do painel, rode com --remover-locais para liberar o disco.');

        return $failures ? self::FAILURE : self::SUCCESS;
    }

    /** Cria o vídeo, envia as partes nas URLs pré-assinadas e fecha o envio. Devolve o id na Noxem. */
    private function upload(NoxemClient $client, Video $video, string $path, int $size): string
    {
        $created = $client->createVideo($video->title, $size, $video->video_original_name ?: basename($path), $video->video_mime);
        $sessionId = null;

        try {
            $upload = $client->createUpload($created['id'], $size);
            $sessionId = $upload['session']['id'];
            $partSize = (int) $upload['session']['partSize'];
            $partCount = (int) $upload['session']['partCount'];
            $urls = collect($upload['parts'])->keyBy('partNumber');

            $handle = fopen($path, 'rb');
            $bar = $this->output->createProgressBar($partCount);

            try {
                for ($n = 1; $n <= $partCount; $n++) {
                    if (! $urls->has($n)) {
                        $signed = $client->signParts($sessionId, range($n, min($partCount, $n + 99)));
                        $urls = $urls->replace(collect($signed['parts'])->keyBy('partNumber'));
                    }

                    $part = $urls->get($n);
                    $body = stream_get_contents($handle, (int) $part['size'], ($n - 1) * $partSize);
                    $this->putPart($client, $sessionId, $n, $part['url'], $body);
                    $bar->advance();
                }
            } finally {
                fclose($handle);
                $bar->finish();
                $this->newLine();
            }

            $client->completeUpload($sessionId);

            return $created['id'];
        } catch (Throwable $e) {
            try {
                if ($sessionId) {
                    $client->abortUpload($sessionId);
                }
                $client->deleteVideo($created['id']);
            } catch (NoxemException) {
                // A falha original é a que importa.
            }

            throw $e;
        }
    }

    /** PUT da parte direto no storage (sem a API Key). URL recusada: pede outra e tenta de novo. */
    private function putPart(NoxemClient $client, string $sessionId, int $n, string $url, string $body): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::timeout(600)->withBody($body, 'application/octet-stream')->put($url);

                if ($response->successful()) {
                    return;
                }

                $error = new RuntimeException("parte {$n}: o storage respondeu {$response->status()}");
            } catch (ConnectionException $e) {
                $error = $e;
            }

            if ($attempt >= 4) {
                throw $error;
            }

            sleep($attempt * 2);
            $url = $client->signParts($sessionId, [$n])['parts'][0]['url'];
        }
    }

    private function removeLocal(NoxemClient $client): int
    {
        $disk = Storage::disk('local');
        $removed = 0;

        foreach ($this->query()->whereNotNull('noxem_video_id')->whereNotNull('video_path')->get() as $video) {
            try {
                $status = $client->video($video->noxem_video_id)['status'] ?? null;
            } catch (NoxemException $e) {
                $this->warn("#{$video->id} {$video->title}: {$e->getMessage()}");

                continue;
            }

            $video->forceFill(['noxem_status' => $status])->saveQuietly();

            if ($status !== 'ready') {
                $this->line("#{$video->id} {$video->title}: ainda {$status} na Noxem, arquivo local mantido.");

                continue;
            }

            $disk->delete($video->video_path);
            $video->forceFill(['video_path' => null])->saveQuietly();
            $removed++;
            $this->info("#{$video->id} {$video->title}: arquivo local apagado (".Bytes::human($video->video_size).').');
        }

        $this->line("{$removed} arquivo(s) local(is) apagado(s).");

        return self::SUCCESS;
    }

    private function query()
    {
        return Video::query()->when($this->option('id'), fn ($q, $ids) => $q->whereIn('id', $ids))->orderBy('id');
    }
}
