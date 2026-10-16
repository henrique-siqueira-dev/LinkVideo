<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Visualizações do link fixo de um filme em um dia. Cada IP conta uma vez por filme
 * (atualizar a página ou abrir de novo não soma): o IP fica em video_viewers.
 */
class VideoDailyView extends Model
{
    public $timestamps = false;

    protected $fillable = ['video_id', 'day', 'views'];

    protected function casts(): array
    {
        return ['day' => 'date', 'views' => 'integer'];
    }

    /** Robôs que abrem o link só para montar a prévia (WhatsApp, Facebook, Telegram, buscadores...). */
    private const BOTS = '/whatsapp|facebookexternalhit|facebot|telegrambot|twitterbot|slackbot|discordbot|linkedinbot|skypeuripreview|googlebot|bingbot|applebot|bot\b|crawler|spider|preview/i';

    public static function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || (bool) preg_match(self::BOTS, $agent);
    }

    /** Soma uma visualização de hoje se este IP ainda não tinha aberto o filme (seguro com acessos simultâneos). */
    public static function record(Video $video, Request $request): void
    {
        $firstTime = DB::table('video_viewers')->insertOrIgnore([
            'video_id' => $video->id,
            'ip' => static::viewerKey($request),
            'created_at' => now(),
        ]);
        if (! $firstTime) {
            return;
        }

        static::query()->upsert(
            [['video_id' => $video->id, 'day' => now()->toDateString(), 'views' => 1]],
            ['video_id', 'day'],
            ['views' => DB::raw('views + 1')],
        );
    }

    /**
     * Quem está assistindo: o IP. No IPv6 vale a rede /64, porque o mesmo aparelho troca
     * o final do endereço com frequência (e sem isso contaria de novo).
     */
    public static function viewerKey(Request $request): string
    {
        $ip = (string) $request->ip();
        if (str_starts_with($ip, '::ffff:') && filter_var(substr($ip, 7), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return substr($ip, 7);
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return inet_ntop(substr(inet_pton($ip), 0, 8).str_repeat("\0", 8)).'/64';
        }

        return $ip;
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}
