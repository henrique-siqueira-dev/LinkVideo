<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Support\ProtectedMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoxemStreamingTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.noxem.test/api';

    private const VIDEO_ID = 'c3a1b2d4-1111-4abc-9def-0123456789ab';

    private const SESSION_ID = 'e7f1a2b3-4444-4abc-9def-0123456789ab';

    private User $master;

    private string $remoteStatus = 'processing';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'linkvideo.video_storage' => 'noxem',
            'services.noxem.url' => self::API,
            'services.noxem.key' => 'sk_live_INSECURE-test-key',
        ]);
        $this->master = User::factory()->master()->create();

        $ok = fn (array $data) => Http::response(['success' => true, 'data' => $data]);
        $parts = fn (array $numbers) => array_map(fn ($n) => [
            'partNumber' => $n,
            'size' => $n < 3 ? 16777216 : 1000,
            'url' => "https://r2.noxem.test/source?partNumber={$n}",
        ], $numbers);

        Http::fake(function (Request $request) use ($ok, $parts) {
            if (str_starts_with($request->url(), 'https://r2.noxem.test/')) {
                return Http::response('', 200); // PUT da parte no storage
            }

            $path = str_replace(self::API, '', strtok($request->url(), '?'));

            return match ($request->method().' '.$path) {
                'POST /streaming/videos' => $ok(['id' => self::VIDEO_ID, 'status' => 'uploading']),
                'POST /streaming/videos/'.self::VIDEO_ID.'/uploads' => $ok([
                    'session' => ['id' => self::SESSION_ID, 'partSize' => 16777216, 'partCount' => 3],
                    'parts' => $parts([1, 2]),
                    'expiresIn' => 7200,
                ]),
                'POST /streaming/uploads/'.self::SESSION_ID.'/parts' => $ok(['parts' => $parts($request['partNumbers']), 'expiresIn' => 7200]),
                'POST /streaming/uploads/'.self::SESSION_ID.'/complete' => $ok(['video' => ['id' => self::VIDEO_ID, 'status' => 'processing']]),
                'POST /streaming/uploads/'.self::SESSION_ID.'/abort' => $ok(['success' => true]),
                'PATCH /streaming/videos/'.self::VIDEO_ID => $ok(['id' => self::VIDEO_ID]),
                'GET /streaming/videos/'.self::VIDEO_ID => $ok(['id' => self::VIDEO_ID, 'status' => $this->remoteStatus, 'failureReason' => null]),
                'POST /streaming/videos/'.self::VIDEO_ID.'/playback' => $ok([
                    'url' => 'https://cdn.noxem.test/playback.mp4?X-Amz-Expires=7200',
                    'expiresAt' => now()->addHours(2)->toIso8601String(),
                    'durationSec' => 612,
                ]),
                'DELETE /streaming/videos/'.self::VIDEO_ID => $ok(['success' => true]),
                default => Http::response(['success' => false, 'error' => ['message' => 'rota inesperada']], 404),
            };
        });
    }

    /** Abre o envio, "envia" as partes e fecha, como o navegador faz. Devolve o token. */
    private function uploadToNoxem(): string
    {
        $start = $this->actingAs($this->master)->postJson('/uploads/noxem', [
            'filename' => 'aula.mp4',
            'filesize' => 16777216 * 2 + 1000,
            'content_type' => 'video/mp4',
        ])->assertOk()
            ->assertJsonPath('partSize', 16777216)
            ->assertJsonPath('partCount', 3)
            ->assertJsonCount(2, 'parts');

        $token = $start->json('token');

        // A parte 3 não veio no primeiro lote: o navegador pede a URL dela.
        $this->postJson("/uploads/noxem/{$token}/parts", ['part_numbers' => [3]])
            ->assertOk()
            ->assertJsonPath('parts.0.partNumber', 3);

        $this->postJson("/uploads/noxem/{$token}/complete")->assertOk()->assertJson(['done' => true, 'token' => $token]);

        return $token;
    }

    private function noxemVideo(array $attributes = []): Video
    {
        Storage::disk('local')->put('covers/c.png', 'png');

        return Video::create([
            'title' => 'Aula 1',
            'genre' => 'drama',
            'age_rating' => 'L',
            'cover_path' => 'covers/c.png',
            'video_path' => null,
            'noxem_video_id' => self::VIDEO_ID,
            'noxem_status' => 'processing',
            'video_mime' => 'video/mp4',
            'video_size' => 1000,
            ...$attributes,
        ]);
    }

    public function test_master_uploads_to_noxem_and_registers_video_without_local_file(): void
    {
        $token = $this->uploadToNoxem();

        $this->post('/videos', [
            'title' => 'Aula de violão',
            'genre' => 'drama',
            'age_rating' => 'L',
            'cover' => UploadedFile::fake()->image('capa.jpg', 800, 1200),
            'video_token' => $token,
        ])->assertRedirect('/videos');

        $video = Video::sole();
        $this->assertNull($video->video_path);
        $this->assertSame(self::VIDEO_ID, $video->noxem_video_id);
        $this->assertSame('processing', $video->noxem_status);
        $this->assertSame(16777216 * 2 + 1000, $video->video_size);
        $this->assertSame([], Storage::disk('local')->files('videos'));

        // A API Key vai só no cabeçalho, e o título do painel é copiado para a Noxem.
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer sk_live_INSECURE-test-key')
            && ! str_contains($r->url(), 'sk_live'));
        Http::assertSent(fn (Request $r) => $r->method() === 'PATCH' && $r['title'] === 'Aula de violão');

        // O token só vale uma vez.
        $this->post('/videos', [
            'title' => 'De novo', 'genre' => 'drama', 'age_rating' => 'L',
            'cover' => UploadedFile::fake()->image('capa.jpg'), 'video_token' => $token,
        ])->assertSessionHasErrors('video_token');
    }

    public function test_upload_token_belongs_to_the_user_who_started_it(): void
    {
        $start = $this->actingAs($this->master)->postJson('/uploads/noxem', [
            'filename' => 'aula.mp4', 'filesize' => 5000,
        ])->assertOk();

        $other = User::factory()->master()->create();
        $this->actingAs($other)->postJson("/uploads/noxem/{$start->json('token')}/complete")
            ->assertUnprocessable()->assertJsonValidationErrors('chunk');
        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/complete'));
    }

    public function test_rejects_unsupported_format_and_oversized_file_before_calling_the_api(): void
    {
        config(['linkvideo.uploads.video_max_mb' => 1]);

        $this->actingAs($this->master)->postJson('/uploads/noxem', ['filename' => 'virus.exe', 'filesize' => 10])
            ->assertJsonValidationErrors('chunk');
        $this->postJson('/uploads/noxem', ['filename' => 'grande.mp4', 'filesize' => 2 * 1024 * 1024])
            ->assertJsonValidationErrors('chunk');

        Http::assertNothingSent();
    }

    public function test_aborting_cancels_the_session_and_deletes_the_empty_video(): void
    {
        $token = $this->actingAs($this->master)->postJson('/uploads/noxem', ['filename' => 'a.mp4', 'filesize' => 100])->json('token');

        $this->postJson("/uploads/noxem/{$token}/abort")->assertOk();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/'.self::SESSION_ID.'/abort'));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE');
    }

    public function test_noxem_upload_routes_are_off_when_storage_is_local(): void
    {
        config(['linkvideo.video_storage' => 'local']);

        $this->actingAs($this->master)->postJson('/uploads/noxem', ['filename' => 'a.mp4', 'filesize' => 100])->assertNotFound();
    }

    public function test_stream_waits_while_processing_then_redirects_to_cdn(): void
    {
        $video = $this->noxemVideo();

        $this->actingAs($this->master)->get("/videos/{$video->id}/stream")->assertStatus(503);
        $this->assertSame('processing', $video->fresh()->noxem_status);

        $this->remoteStatus = 'ready';
        $this->travel(20)->seconds(); // passa o intervalo mínimo entre consultas de status

        $this->get("/videos/{$video->id}/stream")
            ->assertRedirect('https://cdn.noxem.test/playback.mp4?X-Amz-Expires=7200')
            ->assertHeader('Referrer-Policy', 'no-referrer');

        $video->refresh();
        $this->assertSame('ready', $video->noxem_status);
        $this->assertSame(612, $video->duration);

        // A URL da CDN fica em cache: o segundo play não chama a API de novo.
        $this->get("/videos/{$video->id}/stream")->assertRedirect();
        Http::assertSentCount(3); // status (processing), status (ready), playback
    }

    public function test_public_film_link_redirects_signed_request_to_cdn(): void
    {
        $video = $this->noxemVideo(['noxem_status' => 'ready', 'slug' => 'aula-1']);

        $request = \Illuminate\Http\Request::create('/filme/aula-1', 'GET', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $url = ProtectedMedia::signedUrl('film.stream', ['slug' => 'aula-1'], $request);

        $this->get($url, ['Sec-Fetch-Dest' => 'video'])->assertRedirect('https://cdn.noxem.test/playback.mp4?X-Amz-Expires=7200');
        $this->get($url, ['Sec-Fetch-Dest' => 'document'])->assertForbidden();
        $this->get('/filme/aula-1/video', ['Sec-Fetch-Dest' => 'video'])->assertForbidden(); // sem assinatura
    }

    public function test_film_page_shows_preparing_notice_instead_of_player(): void
    {
        $this->noxemVideo(['slug' => 'aula-1']);

        $this->get('/filme/aula-1')->assertOk()
            ->assertSee('Este filme está sendo preparado.')
            ->assertDontSee('<video', false);
    }

    public function test_film_page_of_failed_video_says_unavailable(): void
    {
        $this->noxemVideo(['slug' => 'aula-1', 'noxem_status' => 'failed', 'noxem_error' => 'Codec não suportado']);

        $this->get('/filme/aula-1')->assertOk()
            ->assertSee('Este filme não está disponível no momento.')
            ->assertDontSee('Codec não suportado'); // o motivo técnico fica só no painel
    }

    public function test_migrated_video_keeps_playing_local_file_until_noxem_is_ready(): void
    {
        Storage::disk('local')->put('videos/v.mp4', str_repeat('x', 1000));
        $video = $this->noxemVideo(['video_path' => 'videos/v.mp4']);

        $this->actingAs($this->master)->get("/videos/{$video->id}/stream")->assertOk();
        $this->assertSame('ready', $video->playbackState());
    }

    public function test_deleting_video_removes_it_from_noxem(): void
    {
        $video = $this->noxemVideo(['noxem_status' => 'ready']);

        $this->actingAs($this->master)->delete("/videos/{$video->id}")->assertRedirect('/videos');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/streaming/videos/'.self::VIDEO_ID));
        $this->assertDatabaseCount('videos', 0);
    }

    public function test_video_list_shows_noxem_status(): void
    {
        $this->noxemVideo();
        $this->remoteStatus = 'ready';

        $this->actingAs($this->master)->get('/videos')->assertOk()->assertSee('Noxem Cloud');
        $this->assertSame('ready', Video::sole()->noxem_status);
    }

    public function test_migration_command_uploads_local_videos(): void
    {
        Storage::disk('local')->put('videos/v.mp4', str_repeat('x', 16777216 * 2 + 1000));
        $video = $this->noxemVideo(['video_path' => 'videos/v.mp4', 'noxem_video_id' => null, 'noxem_status' => null]);

        $this->artisan('linkvideo:noxem-migrar')->assertSuccessful();

        $video->refresh();
        $this->assertSame(self::VIDEO_ID, $video->noxem_video_id);
        $this->assertSame('videos/v.mp4', $video->video_path); // local continua até a Noxem terminar
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && str_contains($r->url(), 'partNumber=3') && strlen($r->body()) === 1000);
        Http::assertSent(fn (Request $r) => $r->method() === 'PUT' && ! $r->hasHeader('Authorization'));

        $this->remoteStatus = 'ready';
        $this->artisan('linkvideo:noxem-migrar', ['--remover-locais' => true])->assertSuccessful();
        $this->assertNull($video->fresh()->video_path);
        Storage::disk('local')->assertMissing('videos/v.mp4');
    }
}
