<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The auth stub has to resolve the demo user everywhere Laravel looks for a
 * user — not just on the request — or policies, the Gate and auth()->id()
 * silently see a guest.
 */
class ActAsDemoUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('probe', fn (User $user) => true);

        Route::middleware('web')->get('/_auth-probe', fn () => [
            'request_user' => request()->user()?->email,
            'auth_user' => auth()->user()?->email,
            'auth_id' => auth()->id(),
            'auth_check' => auth()->check(),
            'gate_allows' => Gate::allows('probe'),
        ]);
    }

    public function test_every_auth_entry_point_resolves_the_demo_user(): void
    {
        $this->seed();
        $demo = User::query()->where('email', 'demo@street.example')->sole();

        $this->get('/_auth-probe')
            ->assertOk()
            ->assertExactJson([
                'request_user' => 'demo@street.example',
                'auth_user' => 'demo@street.example',
                'auth_id' => $demo->id,
                'auth_check' => true,
                'gate_allows' => true,
            ]);
    }

    public function test_requests_are_guests_until_the_database_is_seeded(): void
    {
        $this->get('/_auth-probe')
            ->assertOk()
            ->assertExactJson([
                'request_user' => null,
                'auth_user' => null,
                'auth_id' => null,
                'auth_check' => false,
                'gate_allows' => false,
            ]);
    }

    /**
     * Tests need to act as someone other than the demo user (e.g. to prove one
     * user can't touch another's data), so the stub must not override them.
     */
    public function test_an_explicitly_authenticated_user_is_not_replaced(): void
    {
        $this->seed();
        $other = User::factory()->create();

        $this->actingAs($other)
            ->get('/_auth-probe')
            ->assertOk()
            ->assertJsonPath('request_user', $other->email)
            ->assertJsonPath('auth_user', $other->email);
    }
}
