<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Support\CoverImage;
use App\Support\VideoStream;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entrega capa e vídeo apenas para usuários logados. Os arquivos ficam em
 * storage/app/private, fora da pasta pública. O BinaryFileResponse responde
 * a requisições Range, o que permite avançar/voltar o vídeo no player.
 * Vídeo na Noxem Cloud: redireciona para a CDN (App\Support\VideoStream).
 */
class MediaController extends Controller
{
    public function stream(Video $video): Response
    {
        return VideoStream::respond($video, fn () => $this->file($video->video_path, $video->video_mime, [
            'Cache-Control' => 'private, no-transform',
        ]));
    }

    public function cover(Video $video): BinaryFileResponse
    {
        return $this->file(CoverImage::path($video->cover_path), null, [
            'Cache-Control' => 'private, max-age=2592000',
        ]);
    }

    private function file(string $relativePath, ?string $mime, array $headers): BinaryFileResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($relativePath), 404);

        if ($mime) {
            $headers['Content-Type'] = $mime;
        }

        return response()->file($disk->path($relativePath), $headers);
    }
}
