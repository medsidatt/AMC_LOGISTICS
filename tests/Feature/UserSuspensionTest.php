<?php

namespace Tests\Feature;

use App\Models\Auth\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Server-side enforcement of account suspension. A suspended account must not
 * retain access through any authenticated entry point. DatabaseTransactions
 * keeps the dev DB untouched (each test is rolled back).
 */
class UserSuspensionTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(bool $suspended): User
    {
        $user = new User();
        $user->name = 'Suspension Test';
        $user->email = 'suspension-'.uniqid().'@example.test';
        $user->password = 'password';
        $user->is_suspended = $suspended;
        $user->save();

        return $user;
    }

    public function test_suspended_user_is_bounced_to_login(): void
    {
        $user = $this->makeUser(suspended: true);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login');
    }

    public function test_active_user_is_not_bounced_by_suspension_guard(): void
    {
        $user = $this->makeUser(suspended: false);

        $location = (string) $this->actingAs($user)
            ->get('/dashboard')
            ->headers->get('Location');

        // The suspension guard must not redirect an active account to /login.
        $this->assertStringNotContainsString('/login', $location);
    }
}
