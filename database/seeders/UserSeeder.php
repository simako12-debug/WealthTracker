<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{name:string,email:string,password:string} $config */
        $config = config('app.seed_user');

        if (empty($config['password'])) {
            throw new \RuntimeException('SEED_USER_PASSWORD must be set');
        }

        User::query()->updateOrCreate(
            ['email' => $config['email']],
            [
                'name' => $config['name'],
                'password' => Hash::make($config['password']),
                'email_verified_at' => now(),
            ],
        );
    }
}
