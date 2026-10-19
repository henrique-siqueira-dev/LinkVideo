<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_every_master_and_keeps_changed_passwords_on_next_deploy(): void
    {
        config([
            'linkvideo.master_password' => 'INSECURE-seed-test-1',
            'linkvideo.admin_password' => 'INSECURE-admin-test-1',
        ]);

        $this->seed();

        $emails = collect(config('linkvideo.masters'))->pluck('email')->push('admin@linkvideo.com');
        $this->assertContains('dentistaguilhermebonfim@gmail.com', $emails);
        $this->assertSame($emails->count(), User::count());
        User::all()->each(fn (User $user) => $this->assertTrue($user->isMaster()));

        // Alguém troca a senha; o próximo deploy roda o seeder de novo e não mexe nela.
        $user = User::where('email', 'dentistaguilhermebonfim@gmail.com')->sole();
        $user->update(['password' => 'INSECURE-trocada']);
        $this->seed();

        $this->assertSame($emails->count(), User::count());
        $this->assertTrue(Hash::check('INSECURE-trocada', $user->fresh()->password));
    }

    public function test_admin_uses_its_own_password(): void
    {
        config([
            'linkvideo.master_password' => 'INSECURE-seed-test-1',
            'linkvideo.admin_password' => 'INSECURE-admin-test-1',
        ]);

        $this->seed();

        $admin = User::where('email', 'admin@linkvideo.com')->sole();
        $this->assertTrue($admin->isMaster());
        $this->assertTrue(Hash::check('INSECURE-admin-test-1', $admin->password));
    }

    public function test_without_password_it_skips_new_masters_instead_of_failing(): void
    {
        config(['linkvideo.master_password' => null, 'linkvideo.admin_password' => null]);

        $this->seed();

        $this->assertSame(0, User::count());
    }
}
