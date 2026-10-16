<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/videos/create')->assertRedirect('/login');
        $this->post('/uploads/chunk')->assertRedirect('/login');
    }

    public function test_master_can_log_in_and_out(): void
    {
        $user = User::factory()->master()->create(['email' => 'master@example.com']);

        $this->post('/login', ['email' => 'master@example.com', 'password' => 'password'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->master()->create(['email' => 'master@example.com']);

        $this->from('/login')
            ->post('/login', ['email' => 'master@example.com', 'password' => 'errada'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_regular_user_sees_library_but_cannot_manage_videos(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('Usuários')->assertDontSee('Configurações');
        $this->actingAs($user)->get('/videos')->assertForbidden();
        $this->actingAs($user)->get('/videos/create')->assertForbidden();
        $this->actingAs($user)->post('/uploads/chunk')->assertForbidden();
    }

    public function test_master_sees_management_pages(): void
    {
        $master = User::factory()->master()->create();

        $this->actingAs($master)->get('/dashboard')->assertOk()->assertSee('Visualizações por dia')->assertSee('Configurações')->assertDontSee('Usuários')->assertDontSee('Financeiro');
        // Usuários saiu do menu lateral; o acesso fica em Configurações.
        $this->actingAs($master)->get('/configuracoes')->assertOk()->assertSee(route('members.index'));
        $this->actingAs($master)->get('/videos')->assertOk()->assertDontSee('>Valor<', false)->assertDontSee('>Classif.<', false);
        $this->actingAs($master)->get('/videos/create')->assertOk()->assertSee('Classificação indicativa');
    }

    public function test_https_app_url_forces_https_links(): void
    {
        config(['app.url' => 'https://www.agenciajohncode.com.br/sistemas/linkvideo']);
        $this->app->getProvider(AppServiceProvider::class)->boot();

        $this->assertStringStartsWith('https://', route('login'));
    }
}
