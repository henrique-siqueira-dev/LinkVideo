<?php

namespace App\Support;

use App\Models\Video;
use App\Models\VideoDailyView;
use Illuminate\Support\Collection;

/** Números do Início: visualizações dos links fixos. */
class Stats
{
    public static function overview(): array
    {
        $views = fn (int $days) => (int) VideoDailyView::where('day', '>=', now()->subDays($days - 1)->toDateString())->sum('views');

        return [
            'views_today' => $views(1),
            'views_7' => $views(7),
            'views_30' => $views(30),
            'views_total' => (int) VideoDailyView::sum('views'),
            'videos' => Video::count(),
        ];
    }

    /** Últimos N dias: rótulos "dd/mm" e visualizações por dia. */
    public static function daily(int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $byDay = VideoDailyView::where('day', '>=', $start->toDateString())
            ->get(['day', 'views'])
            ->groupBy(fn ($row) => $row->day->format('Y-m-d'));

        $labels = $views = [];
        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $labels[] = $day->format('d/m');
            $views[] = (int) $byDay->get($day->format('Y-m-d'), collect())->sum('views');
        }

        return compact('labels', 'views');
    }

    /** Filmes mais assistidos nos últimos N dias. */
    public static function topVideos(int $days = 30, int $limit = 5): Collection
    {
        return Video::query()
            ->withSum(['dailyViews as views_period' => fn ($q) => $q->where('day', '>=', now()->subDays($days - 1)->toDateString())], 'views')
            ->orderByDesc('views_period')
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title'])
            ->filter(fn ($video) => (int) $video->views_period > 0)
            ->values();
    }
}
