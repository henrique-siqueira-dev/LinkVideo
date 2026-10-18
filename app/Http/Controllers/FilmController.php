<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\VideoDailyView;
use App\Support\CoverImage;
use App\Support\Noxem\NoxemPlayback;
use App\Support\ProtectedMedia;
use App\Support\VideoStream;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Link fixo de cada filme (…/filme/{slug}): abre o player direto, sem login.
 * Qualquer pessoa com o link assiste (é o link usado na resposta automática do WhatsApp).
 * O que se protege é o arquivo: sem botão de baixar, sem clique direito, e o endereço
 * do vídeo é assinado, preso ao IP de quem abriu a página e recusado se aberto numa aba.
 */
class FilmController extends Controller
{
    public function show(Request $request, string $slug, NoxemPlayback $noxem): Response
    {
        $video = $this->find($slug);

        // Conta a visualização: uma por IP em cada filme. Não conta prévia de robôs nem o master conferindo pelo painel.
        if (! VideoDailyView::isBot($request) && ! $request->user()?->isMaster()) {
            VideoDailyView::record($video, $request);
        }

        // Vídeo na Noxem ainda em preparo: confere se já ficou pronto antes de montar a página.
        $noxem->refresh($video);

        $response = response()->view('film.watch', [
            'video' => $video,
            'streamUrl' => ProtectedMedia::signedUrl('film.stream', ['slug' => $video->slug], $request),
            'coverUrl' => ProtectedMedia::signedUrl('film.cover', ['slug' => $video->slug], $request),
            'footer' => config('app.name'),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    public function stream(Request $request, string $slug): Response
    {
        $video = $this->authorizeMedia($request, $slug);

        return VideoStream::respond($video, fn () => ProtectedMedia::file($video->video_path, [
            'Content-Type' => $video->video_mime,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store, no-transform',
        ]));
    }

    public function cover(Request $request, string $slug): BinaryFileResponse
    {
        $video = $this->authorizeMedia($request, $slug);

        return ProtectedMedia::file(CoverImage::path($video->cover_path), ['Cache-Control' => 'private, max-age=3600']);
    }

    private function find(string $slug): Video
    {
        return Video::where('slug', $slug)->firstOrFail();
    }

    private function authorizeMedia(Request $request, string $slug): Video
    {
        $video = $this->find($slug);
        ProtectedMedia::blockDirectOpen($request);
        abort_unless($request->user()?->isMaster() || ProtectedMedia::hasValidSignature($request), 403);

        return $video;
    }
}
