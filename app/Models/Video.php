<?php

namespace App\Models;

use App\Enums\AgeRating;
use App\Enums\Genre;
use App\Support\Bytes;
use App\Support\CoverImage;
use App\Support\Noxem\NoxemPlayback;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Video extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'slug',
        'description',
        'genre',
        'age_rating',
        'price_cents',
        'cover_path',
        'video_path',
        'noxem_video_id',
        'noxem_status',
        'noxem_error',
        'video_original_name',
        'video_mime',
        'video_size',
        'duration',
        'video_width',
        'video_height',
    ];

    protected function casts(): array
    {
        return [
            'genre' => Genre::class,
            'age_rating' => AgeRating::class,
            'video_size' => 'integer',
            'duration' => 'integer',
            'video_width' => 'integer',
            'video_height' => 'integer',
            'price_cents' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Video $video) {
            $video->slug ??= static::uniqueSlug($video->title);
        });

        // Ao excluir o registro, remove também os arquivos do disco e o vídeo na Noxem Cloud.
        static::deleted(function (Video $video) {
            Storage::disk('local')->delete(array_filter([$video->cover_path, $video->video_path]));
            CoverImage::forget($video->cover_path);
            app(NoxemPlayback::class)->forget($video->noxem_video_id);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyViews(): HasMany
    {
        return $this->hasMany(VideoDailyView::class);
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['q'] ?? null, fn (Builder $q, string $term) => $q->where('title', 'like', '%'.$term.'%'))
            ->when(Genre::tryFrom($filters['genre'] ?? ''), fn (Builder $q, Genre $genre) => $q->where('genre', $genre))
            ->when(AgeRating::tryFrom($filters['rating'] ?? ''), fn (Builder $q, AgeRating $rating) => $q->where('age_rating', $rating));
    }

    public function coverUrl(): string
    {
        return route('videos.cover', ['video' => $this, 'v' => $this->updated_at?->timestamp]);
    }

    /** Gera um endereço curto e único a partir do título (ex.: "amor-em-lisboa", "amor-em-lisboa-2"). */
    public static function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 100, '') ?: 'filme';
        $slug = $base;

        for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /**
     * Link fixo do filme: abre o player direto (sem login), sem opção de baixar.
     * Não muda se o título for editado, para não quebrar links já divulgados.
     */
    public function fixedUrl(): string
    {
        if (! $this->slug) {
            $this->forceFill(['slug' => static::uniqueSlug($this->title)])->saveQuietly();
        }

        return route('film.show', $this->slug);
    }

    public function streamUrl(): string
    {
        return route('videos.stream', $this);
    }

    /** Vídeo guardado na Noxem Cloud e já pronto para tocar pela CDN. */
    public function isNoxemReady(): bool
    {
        return $this->noxem_video_id !== null && $this->noxem_status === 'ready';
    }

    /**
     * Situação para o player: "ready" (toca), "processing" (Noxem ainda preparando)
     * ou "failed". Enquanto a cópia da Noxem não fica pronta, um arquivo local (vídeo
     * migrado) continua tocando.
     */
    public function playbackState(): string
    {
        if (! $this->noxem_video_id || $this->isNoxemReady() || $this->video_path) {
            return 'ready';
        }

        return $this->noxem_status === 'failed' ? 'failed' : 'processing';
    }

    public function formattedDuration(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        $hours = intdiv($this->duration, 3600);
        $minutes = intdiv($this->duration % 3600, 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}min" : "{$hours}h";
        }

        return $minutes > 0 ? "{$minutes}min" : "{$seconds}s";
    }

    /** Vídeo gravado em pé (celular)? null quando o formato ainda não é conhecido. */
    public function isVertical(): ?bool
    {
        return $this->video_width && $this->video_height ? $this->video_height > $this->video_width : null;
    }

    public function formattedSize(): string
    {
        return Bytes::human($this->video_size);
    }

    public function coverSize(): int
    {
        try {
            return Storage::disk('local')->size($this->cover_path);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Espaço que o vídeo ocupa no servidor (arquivo de vídeo local + capa). Vídeo na Noxem: só a capa. */
    public function diskUsage(): int
    {
        return ($this->video_path ? $this->video_size : 0) + $this->coverSize();
    }

    public function formattedDiskUsage(): string
    {
        return Bytes::human($this->diskUsage());
    }

    /** Espaço total ocupado por todos os vídeos e capas. */
    public static function totalDiskUsage(): int
    {
        return (int) static::whereNotNull('video_path')->sum('video_size')
            + static::query()->get(['cover_path'])->sum(fn (self $video) => $video->coverSize());
    }

    /** Dados usados pelo modal do player (Alpine.js). */
    public function toPlayerPayload(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'genre' => $this->genre->label(),
            'rating' => $this->age_rating->value,
            'ratingLabel' => $this->age_rating->label(),
            'ratingClasses' => $this->age_rating->badgeClasses(),
            'duration' => $this->formattedDuration(),
            'cover' => $this->coverUrl(),
            'src' => $this->streamUrl(),
            'mime' => $this->video_mime,
            'vertical' => (bool) $this->isVertical(),
            'state' => $this->playbackState(),
        ];
    }
}
