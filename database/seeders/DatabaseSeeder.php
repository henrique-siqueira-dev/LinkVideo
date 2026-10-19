<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $masters = collect(config('linkvideo.masters'))
            ->map(fn (array $master) => $master + ['password' => config('linkvideo.master_password')])
            ->push(config('linkvideo.admin') + ['password' => config('linkvideo.admin_password'), 'password_env' => 'ADMIN_PASSWORD']);

        foreach ($masters as $master) {
            $password = $master['password'];

            $user = User::firstOrNew(['email' => $master['email']]);

            // Só define a senha na criação, para não sobrescrever uma senha trocada depois.
            if (! $user->exists) {
                // Roda a cada deploy: sem senha definida, avisa e segue (não derruba a subida).
                if (blank($password)) {
                    $this->command?->warn('Defina '.($master['password_env'] ?? 'MASTER_PASSWORD')." para criar {$master['email']}.");

                    continue;
                }

                $user->password = $password;
                $user->email_verified_at = now();
            }

            $user->name = $user->name ?: $master['name'];
            $user->role = UserRole::Master;
            $user->save();
        }
    }
}
