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

    public function testRegisterRouteIsRemoved(): void
    {
        $this->get('/register')->assertNotFound();
    }

    public function testPasswordResetRouteIsRemoved(): void
    {
        $this->get('/forgot-password')->assertNotFound();
    }

    public function testSeededUserCanLogIn(): void
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
