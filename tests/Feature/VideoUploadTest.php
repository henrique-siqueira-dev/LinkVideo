<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Video;
use App\Support\ChunkedUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VideoUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->master = User::factory()->master()->create();
    }

    /** Bytes com cabeçalho MP4 válido para o finfo, com o tamanho pedido. */
    private function fakeMp4(int $size): string
    {
        $header = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

        return $header.str_repeat("\x00", $size - strlen($header));
    }

    /** Envia o conteúdo em partes como o navegador faz e devolve o token. */
    private function uploadInChunks(string $content, string $filename = 'filme.mp4'): string
    {
        $chunkSize = ChunkedUpload::chunkSize();
        $chunks = str_split($content, $chunkSize);
        $uploadId = (string) Str::uuid();
        $response = null;

        foreach ($chunks as $index => $chunk) {
            $response = $this->actingAs($this->master)->post('/uploads/chunk', [
                'upload_id' => $uploadId,
                'index' => $index,
                'total' => count($chunks),
                'filename' => $filename,
                'filesize' => strlen($content),
                'chunk' => UploadedFile::fake()->createWithContent('blob', $chunk),
            ])->assertOk();
        }

        $response->assertJson(['done' => true, 'token' => $uploadId]);

        return $uploadId;
    }

    public function test_master_uploads_video_in_chunks_and_registers_it(): void
    {
        $content = $this->fakeMp4(ChunkedUpload::chunkSize() * 2 + 1234); // 3 partes
        $token = $this->uploadInChunks($content);

        $this->actingAs($this->master)->post('/videos', [
            'title' => 'Amor em Paris',
            'description' => 'Um romance.',
            'genre' => 'romance',
            'age_rating' => '12',
            'cover' => UploadedFile::fake()->image('capa.jpg', 800, 1200),
            'video_token' => $token,
            'duration' => 5400,
        ])->assertRedirect('/videos')->assertSessionHas('success');

        $video = Video::sole();
        $this->assertSame('Amor em Paris', $video->title);
        $this->assertSame('Romance', $video->genre->label());
        $this->assertSame('12 anos', $video->age_rating->label());
        $this->assertSame('1h 30min', $video->formattedDuration());
        $this->assertSame('video/mp4', $video->video_mime);
        $this->assertSame(strlen($content), $video->video_size);
        $this->assertSame($content, Storage::disk('local')->get($video->video_path));
        Storage::disk('local')->assertExists($video->cover_path);
        $this->assertEmpty(Storage::disk('local')->files('uploads-tmp'));
    }

    public function test_upload_queue_page_and_json_store_return_the_fixed_link(): void
    {
        $this->actingAs($this->master)->get('/videos/create')
            ->assertOk()->assertSee('Adicionar outro filme')->assertSee('Carregar')
            ->assertSee('Tamanho máximo por envio')->assertSee('4,0 GB');

        // Erro de preenchimento: o upload do vídeo continua válido para tentar de novo.
        $token = $this->uploadInChunks($this->fakeMp4(3000));
        $this->actingAs($this->master)
            ->postJson('/videos', ['title' => '', 'genre' => 'drama', 'age_rating' => '12', 'video_token' => $token,
                'cover' => UploadedFile::fake()->image('c.png')])
            ->assertStatus(422)->assertJsonValidationErrors('title');

        $this->actingAs($this->master)
            ->post('/videos', ['title' => 'Filme da Fila', 'genre' => 'drama', 'age_rating' => '12', 'price' => '49,90',
                'video_token' => $token, 'cover' => UploadedFile::fake()->image('c.png')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('title', 'Filme da Fila')
            ->assertJsonPath('fixed_url', route('film.show', 'filme-da-fila'));

        $this->assertSame(4990, Video::sole()->price_cents);
    }

    public function test_logged_user_can_stream_with_range_and_guest_cannot(): void
    {
        $token = $this->uploadInChunks($this->fakeMp4(5000));
        $this->actingAs($this->master)->post('/videos', [
            'title' => 'Teste', 'genre' => 'acao', 'age_rating' => 'L',
            'cover' => UploadedFile::fake()->image('capa.png'), 'video_token' => $token,
        ]);
        $video = Video::sole();

        $viewer = User::factory()->create();
        $this->actingAs($viewer)
            ->get("/videos/{$video->id}/stream", ['Range' => 'bytes=0-99'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-99/5000');
        $this->actingAs($viewer)->get("/videos/{$video->id}/cover")->assertOk();

        auth()->logout();
        $this->get("/videos/{$video->id}/stream")->assertRedirect('/login');
    }

    public function test_rejects_non_video_files(): void
    {
        $this->actingAs($this->master)->post('/uploads/chunk', [
            'upload_id' => (string) Str::uuid(), 'index' => 0, 'total' => 1,
            'filename' => 'virus.exe', 'filesize' => 10,
            'chunk' => UploadedFile::fake()->createWithContent('blob', '0123456789'),
        ])->assertSessionHasErrors('chunk');

        // Extensão de vídeo, mas conteúdo que não é vídeo.
        $fake = '<?php echo "hacked"; ?>';
        $this->actingAs($this->master)->post('/uploads/chunk', [
            'upload_id' => (string) Str::uuid(), 'index' => 0, 'total' => 1,
            'filename' => 'falso.mp4', 'filesize' => strlen($fake),
            'chunk' => UploadedFile::fake()->createWithContent('blob', $fake),
        ])->assertSessionHasErrors(['chunk' => 'O arquivo enviado não parece ser um vídeo válido.']);
        $this->assertEmpty(Storage::disk('local')->files('uploads-tmp'));
    }

    public function test_cannot_claim_upload_of_another_user(): void
    {
        $token = $this->uploadInChunks($this->fakeMp4(3000));
        $other = User::factory()->master()->create();

        $this->actingAs($other)->post('/videos', [
            'title' => 'Teste', 'genre' => 'acao', 'age_rating' => 'L',
            'cover' => UploadedFile::fake()->image('capa.png'), 'video_token' => $token,
        ])->assertSessionHasErrors('video_token');

        $this->assertSame(0, Video::count());
    }

    public function test_update_replaces_files_and_delete_removes_them(): void
    {
        $token = $this->uploadInChunks($this->fakeMp4(3000));
        $this->actingAs($this->master)->post('/videos', [
            'title' => 'Original', 'genre' => 'drama', 'age_rating' => '14',
            'cover' => UploadedFile::fake()->image('capa.png'), 'video_token' => $token,
        ]);
        $video = Video::sole();
        [$oldVideo, $oldCover] = [$video->video_path, $video->cover_path];

        // Só metadados: arquivos permanecem.
        $this->actingAs($this->master)->put("/videos/{$video->id}", [
            'title' => 'Renomeado', 'genre' => 'terror', 'age_rating' => '18',
        ])->assertRedirect('/videos');
        $this->assertSame($oldVideo, $video->fresh()->video_path);

        // Novo vídeo e nova capa: antigos são apagados.
        $newToken = $this->uploadInChunks($this->fakeMp4(4000), 'novo.mov');
        $this->actingAs($this->master)->put("/videos/{$video->id}", [
            'title' => 'Renomeado', 'genre' => 'terror', 'age_rating' => '18',
            'cover' => UploadedFile::fake()->image('nova.png'), 'video_token' => $newToken,
        ])->assertRedirect('/videos');

        $video->refresh();
        $this->assertSame('Renomeado', $video->title);
        $this->assertSame(4000, $video->video_size);
        Storage::disk('local')->assertMissing([$oldVideo, $oldCover]);
        Storage::disk('local')->assertExists([$video->video_path, $video->cover_path]);

        $this->actingAs($this->master)->delete("/videos/{$video->id}")->assertRedirect('/videos');
        $this->assertSame(0, Video::count());
        Storage::disk('local')->assertMissing([$video->video_path, $video->cover_path]);
    }

    public function test_library_filters_by_genre_and_search(): void
    {
        foreach ([['Ação total', 'acao'], ['Amor em Paris', 'romance']] as [$title, $genre]) {
            $token = $this->uploadInChunks($this->fakeMp4(2000));
            $this->actingAs($this->master)->post('/videos', [
                'title' => $title, 'genre' => $genre, 'age_rating' => 'L',
                'cover' => UploadedFile::fake()->image('c.png'), 'video_token' => $token,
            ]);
        }

        $this->actingAs($this->master)->get('/dashboard?genre=romance')
            ->assertSee('Amor em Paris')->assertDontSee('Ação total');
        $this->actingAs($this->master)->get('/dashboard?q=total')
            ->assertSee('Ação total')->assertDontSee('Amor em Paris');
    }
}
