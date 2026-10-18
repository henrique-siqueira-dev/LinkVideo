<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('linkvideo.master_password');

        foreach (config('linkvideo.masters') as $master) {
            $user = User::firstOrNew(['email' => $master['email']]);

            // Só define a senha na criação, para não sobrescrever uma senha trocada depois.
            if (! $user->exists) {
                // Roda a cada deploy: sem senha definida, avisa e segue (não derruba a subida).
                if (blank($password)) {
                    $this->command?->warn("Defina MASTER_PASSWORD para criar {$master['email']}.");

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
