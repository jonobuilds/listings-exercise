<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ListingAlertPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A search for a region no factory listing is in. The alert factory makes
     * a live listing, which would otherwise raise a real alert for a search
     * it happens to match, alongside the one the test creates.
     */
    private function searchMatchingNothing(?User $user = null): SavedSearch
    {
        return SavedSearch::factory()
            ->for($user ?? User::factory())
            ->create(['region' => 'Nowhere', 'max_price' => 300_000]);
    }

    public function test_a_user_sees_only_their_own_alerts_newest_first(): void
    {
        $user = User::factory()->create();
        $older = ListingAlert::factory()->for($user)->create(['created_at' => now()->subDay()]);
        $newer = ListingAlert::factory()->for($user)->create();
        ListingAlert::factory()->create(); // someone else's

        $this->actingAs($user)
            ->get('/alerts')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Alerts/Index')
                ->has('alerts.data', 2)
                ->where('alerts.data.0.id', $newer->id)
                ->where('alerts.data.1.id', $older->id)
                ->has('alerts.data.0.listing.branch')
            );
    }

    public function test_an_alert_shows_the_search_that_matched(): void
    {
        $search = $this->searchMatchingNothing();
        ListingAlert::factory()->for($search->user)->create(['saved_search_id' => $search->id]);

        $this->actingAs($search->user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('alerts.data.0.saved_search.id', $search->id)
                ->where('alerts.data.0.saved_search.criteria.max_price', 300_000)
            );
    }

    /**
     * The user was still told about the listing, so the alert stays.
     */
    public function test_deleting_a_search_keeps_its_alerts(): void
    {
        $search = $this->searchMatchingNothing();
        ListingAlert::factory()->for($search->user)->create(['saved_search_id' => $search->id]);

        $this->actingAs($search->user)->delete("/saved-searches/{$search->id}");

        $this->actingAs($search->user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('alerts.data', 1)
                ->where('alerts.data.0.saved_search', null)
            );
    }

    /**
     * Its link would 404: only live listings are public.
     */
    public function test_alerts_for_listings_no_longer_live_are_hidden(): void
    {
        $user = User::factory()->create();
        ListingAlert::factory()->for($user)->create();
        ListingAlert::factory()->for($user)->create(['listing_id' => Listing::factory()->sold()]);

        $this->actingAs($user)
            ->get('/alerts')
            ->assertInertia(fn (AssertableInertia $page) => $page->has('alerts.data', 1));
    }

    public function test_the_query_count_does_not_grow_with_the_number_of_alerts(): void
    {
        $user = User::factory()->create();

        $queriesFor = function () use ($user): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($user)->get('/alerts')->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $search = $this->searchMatchingNothing($user);
        ListingAlert::factory(2)->for($user)->create(['saved_search_id' => $search->id]);
        $few = $queriesFor();

        ListingAlert::factory(8)->for($user)->create(['saved_search_id' => $search->id]);

        $this->assertSame($few, $queriesFor());
    }

    public function test_guests_are_sent_to_the_listings(): void
    {
        $this->get('/alerts')->assertRedirect('/');
    }
}
