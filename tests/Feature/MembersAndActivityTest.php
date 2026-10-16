<?php

namespace Tests\Feature;

use App\Enums\AgeRating;
use App\Enums\Genre;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MembersAndActivityTest extends TestCase
{
    use RefreshDatabase;

    private User $master;

    protected function setUp(): void
    {
        parent::setUp();
        $this->master = User::factory()->master()->create(['name' => 'João Lucas', 'email' => 'joao@example.com']);
    }

    public function test_master_adds_another_master_who_can_log_in(): void
    {
        $this->actingAs($this->master)->post('/usuarios', [
            'name' => 'Higor', 'email' => 'higor@example.com',
            'password' => 'senhaforte1', 'password_confirmation' => 'senhaforte1',
        ])->assertRedirect('/usuarios');

        $higor = User::where('email', 'higor@example.com')->sole();
        $this->assertTrue($higor->isMaster());

        auth()->logout();
        $this->post('/login', ['email' => 'higor@example.com', 'password' => 'senhaforte1'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($higor);
    }

    public function test_validation_and_protections(): void
    {
        $this->actingAs($this->master)->post('/usuarios', [
            'name' => 'X', 'email' => 'joao@example.com', 'password' => 'curta', 'password_confirmation' => 'outra',
        ])->assertSessionHasErrors(['email', 'password']);

        // Não exclui a si mesmo, nem o último master.
        $this->actingAs($this->master)->delete("/usuarios/{$this->master->id}")->assertSessionHas('error');
        $this->assertNotNull($this->master->fresh());

        $regular = User::factory()->create();
        $this->actingAs($regular)->get('/usuarios')->assertForbidden();
        $this->actingAs($regular)->get('/atividades')->assertForbidden();
    }

    public function test_edit_password_and_delete_keep_the_log(): void
    {
        $other = User::factory()->master()->create(['name' => 'Higor', 'email' => 'higor@example.com']);

        $this->actingAs($this->master)->put("/usuarios/{$other->id}", [
            'name' => 'Higor S.', 'email' => 'higor@example.com', 'password' => 'novasenha1', 'password_confirmation' => 'novasenha1',
        ])->assertRedirect('/usuarios');
        $this->assertTrue(password_verify('novasenha1', $other->fresh()->password));

        $this->actingAs($this->master)->delete("/usuarios/{$other->id}")->assertRedirect('/usuarios');
        $this->assertNull($other->fresh());

        $this->assertDatabaseHas('activity_logs', ['action' => 'member.updated', 'user_name' => 'João Lucas']);
        $this->assertTrue(ActivityLog::where('action', 'member.updated')->sole()->description === 'Editou o usuário Higor S. e trocou a senha');
        $this->assertDatabaseHas('activity_logs', ['action' => 'member.deleted', 'description' => 'Excluiu o usuário Higor S. (higor@example.com)']);
    }

    public function test_actions_are_recorded_with_who_ip_and_device(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('covers/c.png', 'png');
        Storage::disk('local')->put('videos/v.mp4', 'x');

        $this->withServerVariables(['REMOTE_ADDR' => '200.10.20.30', 'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/128.0 Safari/537.36'])
            ->post('/login', ['email' => 'joao@example.com', 'password' => 'errada']);
        $this->post('/login', ['email' => 'joao@example.com', 'password' => 'password']);

        $video = Video::create([
            'user_id' => $this->master->id, 'title' => 'Amor em Lisboa', 'genre' => Genre::Romance, 'age_rating' => AgeRating::Doze,
            'cover_path' => 'covers/c.png', 'video_path' => 'videos/v.mp4', 'video_mime' => 'video/mp4', 'video_size' => 1,
        ]);
        $this->actingAs($this->master)->put("/videos/{$video->id}", ['title' => 'Amor em Lisboa', 'genre' => 'drama', 'age_rating' => '14']);
        $this->actingAs($this->master)->delete("/videos/{$video->id}");

        $actions = ActivityLog::orderBy('id')->pluck('action')->all();
        $this->assertSame(['auth.failed', 'auth.login', 'video.updated', 'video.deleted'], $actions);
        $this->assertSame(['genre', 'age_rating'], ActivityLog::where('action', 'video.updated')->sole()->properties['campos']);

        $failed = ActivityLog::where('action', 'auth.failed')->sole();
        $this->assertNull($failed->user_id);
        $this->assertSame('joao@example.com', $failed->user_name);
        $this->assertSame('200.10.20.30', $failed->ip);
        $this->assertSame('Chrome · Windows', $failed->deviceLabel());

        $this->actingAs($this->master)->get('/atividades')
            ->assertOk()->assertSee('Editou o vídeo “Amor em Lisboa”')->assertSee('Senha errada')->assertSee('200.10.20.30');
        $this->actingAs($this->master)->get('/atividades?group=video')
            ->assertSee('Excluiu o vídeo “Amor em Lisboa”')->assertDontSee('Entrou no sistema');
        $this->actingAs($this->master)->get('/atividades?group=failed')
            ->assertSee('Tentativa de login com senha errada')->assertDontSee('Excluiu o vídeo');
    }
}
