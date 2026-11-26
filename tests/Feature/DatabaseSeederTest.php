<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A fresh install should show the feature working without any setup.
     */
    public function test_the_demo_user_has_a_saved_search_with_alerts(): void
    {
        $this->seed();

        $demo = User::query()->where('email', 'demo@street.example')->sole();

        $this->assertCount(1, $demo->savedSearches);
        $this->assertCount(2, $demo->listingAlerts);
        $this->assertTrue(
            $demo->listingAlerts->every(fn ($alert) => $alert->saved_search_id === $demo->savedSearches->first()->id),
        );
    }
}
