<?php

namespace App\Support;

use App\Models\Video;
use App\Support\Noxem\NoxemException;
use App\Support\Noxem\NoxemPlayback;
use Closure;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega do vídeo depois que o acesso já foi conferido (painel ou link fixo assinado).
 * Na Noxem Cloud: redireciona para a URL temporária do MP4 na CDN (o player pede Range lá;
 * os bytes não passam por este servidor). Senão: o arquivo local.
 */
class VideoStream
{
    /** @param  Closure(): Response  $localFile */
    public static function respond(Video $video, Closure $localFile): Response
    {
        if ($video->noxem_video_id) {
            $url = null;

            try {
                $url = app(NoxemPlayback::class)->url($video);
            } catch (NoxemException $e) {
                report($e);
            }

            if ($url) {
                return redirect()->away($url, 302, [
                    // Cada abertura pede a URL de novo: ela vence e não pode ficar no cache do navegador.
                    'Cache-Control' => 'no-store, private',
                    'Referrer-Policy' => 'no-referrer',
                    'X-Robots-Tag' => 'noindex',
                ]);
            }

            // Ainda preparando (ou Noxem fora do ar): toca o arquivo local se ainda existir.
            if (! $video->video_path) {
                abort(503, 'O vídeo ainda está sendo preparado.', ['Retry-After' => '30']);
            }
        }

        return $localFile();
    }
}
