<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('linkvideo.master_password');

        if (blank($password)) {
            throw new RuntimeException('Defina MASTER_PASSWORD no .env antes de rodar o seeder.');
        }

        foreach (config('linkvideo.masters') as $master) {
            $user = User::firstOrNew(['email' => $master['email']]);

            // Só define a senha na criação, para não sobrescrever uma senha trocada depois.
            if (! $user->exists) {
                $user->password = $password;
                $user->email_verified_at = now();
            }

            $user->name = $user->name ?: $master['name'];
            $user->role = UserRole::Master;
            $user->save();
        }
    }
}
