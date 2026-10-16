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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->only(['q', 'genre', 'rating']);

        return view('videos.index', [
            'videos' => Video::query()->with('user')->withSum('dailyViews as views_total', 'views')->filter($filters)->latest()->paginate(20)->withQueryString(),
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

    public function store(VideoRequest $request, ChunkedUpload $uploads): RedirectResponse|JsonResponse
    {
        $upload = $uploads->claim($request->validated('video_token'), $request->user()->id);

        $video = Video::create([
            ...$request->safe()->only(['title', 'description', 'genre', 'age_rating', 'duration', 'video_width', 'video_height']),
            'price_cents' => Money::parse($request->validated('price')),
            'user_id' => $request->user()->id,
            'cover_path' => $request->file('cover')->store('covers', 'local'),
            'video_path' => $upload['path'],
            'video_mime' => $upload['mime'],
            'video_size' => $upload['size'],
            'video_original_name' => $upload['original_name'],
        ]);

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

    public function update(VideoRequest $request, Video $video, ChunkedUpload $uploads): RedirectResponse
    {
        $data = $request->safe()->only(['title', 'description', 'genre', 'age_rating']);
        $oldFiles = [];

        // Com os valores ocultos o campo vem vazio: em branco mantém o valor atual.
        if ($request->filled('price') || Finance::visible()) {
            $data['price_cents'] = Money::parse($request->validated('price'));
        }

        if ($request->filled('video_token')) {
            $upload = $uploads->claim($request->validated('video_token'), $request->user()->id);
            $oldFiles[] = $video->video_path;

            $data += [
                'video_path' => $upload['path'],
                'video_mime' => $upload['mime'],
                'video_size' => $upload['size'],
                'video_original_name' => $upload['original_name'],
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
        Storage::disk('local')->delete($oldFiles);

        $extra = array_filter([
            isset($data['video_path']) ? 'trocou o arquivo de vídeo' : null,
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

    private function formData(Video $video): array
    {
        return [
            'video' => $video,
            'genres' => Genre::options(),
            'ratings' => AgeRating::cases(),
            'upload' => [
                'url' => route('uploads.chunk'),
                'chunkSize' => ChunkedUpload::chunkSize(),
                'maxBytes' => ChunkedUpload::maxBytes(),
                'maxLabel' => Bytes::human(ChunkedUpload::maxBytes()),
                'extensions' => config('linkvideo.uploads.video_extensions'),
            ],
        ];
    }
}
