<?php

namespace App\Http\Controllers;

use App\Enums\AgeRating;
use App\Enums\Genre;
use App\Http\Requests\VideoRequest;
use App\Models\ActivityLog;
use App\Models\Video;
use App\Support\Bytes;
use App\Support\CoverImage;
use App\Support\ChunkedUpload;
use App\Support\Finance;
use App\Support\Money;
use App\Support\Noxem\NoxemClient;
use App\Support\Noxem\NoxemException;
use App\Support\Noxem\NoxemPlayback;
use App\Support\Noxem\NoxemUploads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(Request $request, NoxemPlayback $noxem): View
    {
        $filters = $request->only(['q', 'genre', 'rating']);
        $videos = Video::query()->with('user')->withSum('dailyViews as views_total', 'views')->filter($filters)->latest()->paginate(20)->withQueryString();
        $noxem->refreshMany($videos->getCollection());

        return view('videos.index', [
            'videos' => $videos,
            'filters' => $filters,
            'genres' => Genre::options(),
            'ratings' => AgeRating::options(),
            'storage' => [
                'used' => Bytes::human(Video::totalDiskUsage()),
                'count' => Video::count(),
            ],
        ]);
    }

    /** Fila de cadastro: vários filmes no mesmo formulário, enviados um de cada vez. */
    public function create(): View
    {
        $data = $this->formData(new Video);
        $data['upload'] += [
            'storeUrl' => route('videos.store'),
            'batchMaxBytes' => config('linkvideo.uploads.batch_max_mb') * 1024 * 1024,
            'batchMaxLabel' => Bytes::human(config('linkvideo.uploads.batch_max_mb') * 1024 * 1024),
        ];

        return view('videos.queue', $data);
    }

    public function store(VideoRequest $request): RedirectResponse|JsonResponse
    {
        $upload = $this->claimUpload($request->validated('video_token'), $request->user()->id);

        $video = Video::create([
            ...$request->safe()->only(['title', 'description', 'genre', 'age_rating', 'duration', 'video_width', 'video_height']),
            'price_cents' => Money::parse($request->validated('price')),
            'user_id' => $request->user()->id,
            'cover_path' => $request->file('cover')->store('covers', 'local'),
            ...$upload,
        ]);
        $this->syncNoxemTitle($video);

        ActivityLog::record('video.created', "Cadastrou o vídeo “{$video->title}” ({$video->formattedSize()})", $video);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $video->id,
                'title' => $video->title,
                'fixed_url' => $video->fixedUrl(),
            ], 201);
        }

        return redirect()->route('videos.index')->with('success', "Vídeo “{$video->title}” cadastrado com sucesso.");
    }

    public function edit(Video $video): View
    {
        return view('videos.form', $this->formData($video));
    }

    public function update(VideoRequest $request, Video $video, NoxemPlayback $noxem): RedirectResponse
    {
        $data = $request->safe()->only(['title', 'description', 'genre', 'age_rating']);
        $oldFiles = [];
        $oldNoxemId = null;

        // Com os valores ocultos o campo vem vazio: em branco mantém o valor atual.
        if ($request->filled('price') || Finance::visible()) {
            $data['price_cents'] = Money::parse($request->validated('price'));
        }

        if ($request->filled('video_token')) {
            $upload = $this->claimUpload($request->validated('video_token'), $request->user()->id);
            $oldFiles[] = $video->video_path;
            $oldNoxemId = $video->noxem_video_id;

            $data += $upload + [
                'duration' => $request->validated('duration'),
                'video_width' => $request->validated('video_width'),
                'video_height' => $request->validated('video_height'),
            ];
        }

        if ($request->hasFile('cover')) {
            $oldFiles[] = $video->cover_path;
            CoverImage::forget($video->cover_path);
            $data['cover_path'] = $request->file('cover')->store('covers', 'local');
        }

        $video->fill($data);
        $changes = array_values(array_intersect(array_keys($video->getDirty()), ['title', 'description', 'genre', 'age_rating', 'price_cents']));
        $video->save();
        Storage::disk('local')->delete(array_filter($oldFiles));
        if ($oldNoxemId && $oldNoxemId !== $video->noxem_video_id) {
            $noxem->forget($oldNoxemId);
        }
        if (in_array('title', $changes, true) || isset($upload)) {
            $this->syncNoxemTitle($video);
        }

        $extra = array_filter([
            isset($upload) ? 'trocou o arquivo de vídeo' : null,
            isset($data['cover_path']) ? 'trocou a capa' : null,
        ]);
        ActivityLog::record('video.updated', "Editou o vídeo “{$video->title}”".($extra ? ' e '.implode(' e ', $extra) : ''), $video, array_filter(['campos' => $changes]));

        return redirect()->route('videos.index')->with('success', "Vídeo “{$video->title}” atualizado.");
    }

    public function destroy(Video $video): RedirectResponse
    {
        $video->delete();
        ActivityLog::record('video.deleted', "Excluiu o vídeo “{$video->title}” (o link fixo dele parou de funcionar)", $video);

        return redirect()->route('videos.index')->with('success', "Vídeo “{$video->title}” excluído.");
    }

    /**
     * Troca o token do envio concluído pelos campos do vídeo, conforme onde ele foi gravado
     * (VIDEO_STORAGE): arquivo local ou vídeo na Noxem Cloud (preparo começa lá).
     */
    private function claimUpload(string $token, int $userId): array
    {
        if (NoxemClient::enabled()) {
            $upload = app(NoxemUploads::class)->claim($token, $userId);

            return [
                'video_path' => null,
                'noxem_video_id' => $upload['video_id'],
                'noxem_status' => 'processing',
                'noxem_error' => null,
                'video_mime' => $upload['mime'],
                'video_size' => $upload['size'],
                'video_original_name' => $upload['original_name'],
            ];
        }

        $upload = app(ChunkedUpload::class)->claim($token, $userId);

        return [
            'video_path' => $upload['path'],
            'noxem_video_id' => null,
            'noxem_status' => null,
            'noxem_error' => null,
            'video_mime' => $upload['mime'],
            'video_size' => $upload['size'],
            'video_original_name' => $upload['original_name'],
        ];
    }

    /** Mesmo título no painel da Noxem (facilita achar o vídeo lá). Falha aqui não impede salvar. */
    private function syncNoxemTitle(Video $video): void
    {
        if (! $video->noxem_video_id) {
            return;
        }

        try {
            app(NoxemClient::class)->updateVideo($video->noxem_video_id, ['title' => mb_substr($video->title, 0, 200)]);
        } catch (NoxemException $e) {
            report($e);
        }
    }

    private function formData(Video $video): array
    {
        return [
            'video' => $video,
            'genres' => Genre::options(),
            'ratings' => AgeRating::cases(),
            'upload' => [
                'driver' => NoxemClient::enabled() ? 'noxem' : 'local',
                'noxem' => NoxemClient::enabled() ? [
                    'startUrl' => route('uploads.noxem.start'),
                    // "__TOKEN__" é trocado no navegador pelo token do envio.
                    'signUrl' => route('uploads.noxem.sign', '__TOKEN__'),
                    'completeUrl' => route('uploads.noxem.complete', '__TOKEN__'),
                    'abortUrl' => route('uploads.noxem.abort', '__TOKEN__'),
                ] : null,
                'url' => route('uploads.chunk'),
                'chunkSize' => ChunkedUpload::chunkSize(),
                'maxBytes' => ChunkedUpload::maxBytes(),
                'maxLabel' => Bytes::human(ChunkedUpload::maxBytes()),
                'extensions' => config('linkvideo.uploads.video_extensions'),
            ],
        ];
    }
}
