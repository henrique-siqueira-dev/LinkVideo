<?php

namespace App\Http\Controllers;

use App\Enums\AgeRating;
use App\Enums\Genre;
use App\Models\Video;
use App\Support\Bytes;
use App\Support\Stats;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->only(['q', 'genre', 'rating']);
        $isMaster = (bool) $request->user()->isMaster();

        return view('dashboard', [
            'videos' => Video::query()->filter($filters)->latest()->paginate(24)->withQueryString(),
            'filters' => $filters,
            'genres' => Genre::options(),
            'ratings' => AgeRating::options(),
            'isMaster' => $isMaster,
            'overview' => $isMaster ? Stats::overview() : null,
            'daily' => $isMaster ? Stats::daily(30) : null,
            'topVideos' => $isMaster ? Stats::topVideos(30, 5) : collect(),
            'storage' => Bytes::human(Video::totalDiskUsage()),
        ]);
    }
}
