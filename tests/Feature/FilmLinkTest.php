<?php

namespace Tests\Feature;

use App\Enums\AgeRating;
use App\Enums\Genre;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoDailyView;
use App\Support\CoverImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilmLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::disk('local')->put('videos/v.mp4', str_repeat('x', 1000));
        Storage::disk('local')->put('covers/c.png', 'png');
        $this->master = User::factory()->master()->create();
    }

    private function makeVideo(string $title = 'Amor em Lisboa'): Video
    {
        return Video::create([
            'user_id' => $this->master->id, 'title' => $title, 'genre' => Genre::Romance, 'age_rating' => AgeRating::Doze,
            'cover_path' => 'covers/c.png', 'video_path' => 'videos/v.mp4', 'video_mime' => 'video/mp4', 'video_size' => 1000,
        ]);
    }

    /** URL assinada do vídeo, como o player recebe na página do link fixo. */
    private function streamPathFromPage(string $slug): string
    {
        $html = $this->get("/filme/{$slug}")->assertOk()->getContent();
        preg_match('/src="([^"]+\/video\?[^"]+)"/', $html, $m);
        $this->assertNotEmpty($m, 'o player deve receber a URL assinada do vídeo');
        $url = html_entity_decode($m[1]);

        return parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);
    }

    public function test_each_film_gets_a_fixed_link_that_does_not_change(): void
    {
        $first = $this->makeVideo('Amor em Lisboa');
        $second = $this->makeVideo('Amor em Lisboa');

        $this->assertSame('amor-em-lisboa', $first->slug);
        $this->assertSame('amor-em-lisboa-2', $second->slug);
        $this->assertStringEndsWith('/filme/amor-em-lisboa', $first->fixedUrl());

        // Trocar o título não muda o link já divulgado.
        $first->update(['title' => 'Amor em Lisboa (versão estendida)']);
        $this->assertSame('amor-em-lisboa', $first->fresh()->slug);

        // Vídeos antigos (sem slug) ganham o link na primeira vez que ele é exibido.
        $first->forceFill(['slug' => null])->saveQuietly();
        $this->assertStringEndsWith('/filme/amor-em-lisboa-versao-estendida', $first->fresh()->fixedUrl());
    }

    public function test_anyone_with_the_link_watches_without_login_and_without_download_option(): void
    {
        $video = $this->makeVideo();

        $this->get("/filme/{$video->slug}")
            ->assertOk()
            ->assertSee('Amor em Lisboa')
            ->assertSee('controlslist="nodownload', false)
            ->assertSee('oncontextmenu="return false"', false)
            // Abre rápido: capa pré-carregada, vídeo já buscando e só o JS leve da tela (sem o do painel).
            ->assertSee('rel="preload" as="image"', false)
            ->assertSee('preload="auto"', false)
            ->assertDontSee('Exibição exclusiva')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $html = $this->get("/filme/{$video->slug}")->getContent();
        $this->assertMatchesRegularExpression('#assets/film-[^"]+\.js#', $html);
        $this->assertDoesNotMatchRegularExpression('#assets/app-[^"]+\.js#', $html);

        $this->get('/filme/nao-existe')->assertNotFound();
    }

    public function test_video_file_only_plays_inside_the_page(): void
    {
        $video = $this->makeVideo();
        $path = $this->streamPathFromPage($video->slug);

        // No player (mesmo IP): toca e permite avançar/voltar.
        $this->call('GET', $path, [], [], [], ['HTTP_RANGE' => 'bytes=0-9', 'HTTP_SEC_FETCH_DEST' => 'video'])
            ->assertStatus(206)->assertHeader('Content-Disposition', 'inline');

        // Endereço do vídeo aberto direto numa aba (para salvar): recusado.
        $this->call('GET', $path, [], [], [], ['HTTP_SEC_FETCH_DEST' => 'document'])->assertForbidden();

        // Endereço copiado e aberto em outro IP: recusado.
        $this->call('GET', $path, [], [], [], ['REMOTE_ADDR' => '203.0.113.9'])->assertForbidden();

        // Sem assinatura: recusado.
        $this->get("/filme/{$video->slug}/video")->assertForbidden();
        $this->get("/filme/{$video->slug}/capa")->assertForbidden();

        // Master logado (prévia no painel) acessa direto.
        $this->actingAs($this->master)->get("/filme/{$video->slug}/video", ['Range' => 'bytes=0-9'])->assertStatus(206);
    }

    public function test_videos_list_offers_the_fixed_link(): void
    {
        $video = $this->makeVideo();

        $this->actingAs($this->master)->get('/videos')
            ->assertOk()->assertSee('Copiar link fixo')->assertSee("/filme/{$video->slug}")->assertDontSee('Administrar clientes');
    }

    public function test_views_are_counted_once_per_ip_without_bots_or_master_preview(): void
    {
        $video = $this->makeVideo();
        $other = $this->makeVideo('Noite no Porto');
        $open = fn (Video $film, string $ip, string $agent = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) Safari/604.1') =>
            $this->call('GET', "/filme/{$film->slug}", [], [], [], ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $agent])->assertOk();

        // Mesmo IP atualizando a página: conta uma vez só.
        $open($video, '200.10.20.30');
        $open($video, '200.10.20.30');
        $open($video, '200.10.20.30');
        // Outro IP: mais um. O mesmo IP em outro filme conta para aquele filme.
        $open($video, '177.55.66.77');
        $open($other, '200.10.20.30');
        // IPv6: o mesmo aparelho troca o final do endereço (mesma rede /64) e não conta de novo.
        $open($video, '2804:7f3:f801:15:1157:a37a:3d9f:16d6');
        $open($video, '2804:7f3:f801:15:aaaa:bbbb:cccc:dddd');
        $open($video, '2804:7f3:f801:99::1');
        // Prévia do WhatsApp e master conferindo pelo painel não contam.
        $open($video, '189.1.2.3', 'WhatsApp/2.24.1 A');
        $this->actingAs($this->master)->get("/filme/{$video->slug}")->assertOk();

        $this->assertSame(4, (int) VideoDailyView::where('video_id', $video->id)->sum('views'));
        $this->assertSame(1, (int) VideoDailyView::where('video_id', $other->id)->sum('views'));

        $this->actingAs($this->master)->get('/dashboard')
            ->assertOk()->assertSee('Visualizações por dia')->assertSee('Filmes mais assistidos')->assertSee('Amor em Lisboa');
    }

    public function test_price_is_hidden_until_password_is_confirmed(): void
    {
        $video = $this->makeVideo();
        $video->update(['price_cents' => 4990]);

        $edit = "/videos/{$video->id}/edit";

        // A lista de vídeos não mostra mais o valor; ele só aparece na edição, com o olhinho liberado.
        $this->actingAs($this->master)->get('/videos')->assertOk()->assertDontSee('49,90')->assertDontSee('R$ ••••');
        $this->actingAs($this->master)->get($edit)->assertSee('oculto · em branco mantém')->assertDontSee('49,90');
        $this->actingAs($this->master)->post('/valores/mostrar', ['password' => 'errada'])->assertSessionHas('finance_error');
        $this->actingAs($this->master)->post('/valores/mostrar', ['password' => 'password'])->assertSessionHas('success');
        $this->actingAs($this->master)->get($edit)->assertSee('value="49,90"', false);

        $this->travel(16)->minutes();
        $this->actingAs($this->master)->get($edit)->assertDontSee('49,90');
    }

    public function test_big_cover_is_served_as_a_light_copy(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('PHP sem GD');
        }
        ob_start();
        imagepng(imagecreatetruecolor(2400, 3600));
        Storage::disk('local')->put('covers/grande.png', ob_get_clean());

        $light = CoverImage::path('covers/grande.png');
        $this->assertSame('covers/otimizadas/grande.jpg', $light);
        $this->assertSame([1080, 1620], array_slice(getimagesize(Storage::disk('local')->path($light)), 0, 2));

        // Capa trocada/excluída: a cópia leve vai junto. Arquivo que não é imagem: entregue como está.
        CoverImage::forget('covers/grande.png');
        Storage::disk('local')->assertMissing($light);
        $this->assertSame('covers/c.png', CoverImage::path('covers/c.png'));
    }

    public function test_player_opens_in_the_video_shape(): void
    {
        $vertical = $this->makeVideo('Vlog em pé');
        $vertical->update(['video_width' => 720, 'video_height' => 1280]);
        $horizontal = $this->makeVideo('Filme deitado');
        $horizontal->update(['video_width' => 1920, 'video_height' => 1080]);

        $this->assertTrue($vertical->isVertical());
        $this->assertFalse($horizontal->isVertical());
        $this->assertStringContainsString('data-vertical="1"', $this->get("/filme/{$vertical->slug}")->getContent());
        $this->assertStringContainsString('data-vertical="0"', $this->get("/filme/{$horizontal->slug}")->getContent());
    }
}
