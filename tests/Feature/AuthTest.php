<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserSeeder::class)]
class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_route_is_removed(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_password_reset_route_is_removed(): void
    {
        $this->get('/forgot-password')->assertNotFound();
    }

    public function test_seeded_user_can_log_in(): void
    {
        config()->set('app.seed_user', [
            'name' => 'Test Owner',
            'email' => 'owner@example.test',
            'password' => 'secret-pw',
        ]);
        $this->seed(UserSeeder::class);

        $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'secret-pw',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
        $this->assertSame(1, User::query()->count());
    }
}
