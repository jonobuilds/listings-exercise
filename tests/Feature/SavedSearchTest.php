<?php

namespace Tests\Feature;

use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_sees_only_their_own_saved_searches(): void
    {
        $user = User::factory()->create();
        SavedSearch::factory(2)->for($user)->create(['max_price' => 300_000]);
        SavedSearch::factory()->create(['max_price' => 300_000]); // someone else's

        $this->actingAs($user)
            ->get('/saved-searches')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('SavedSearches/Index')
                ->has('savedSearches', 2, fn (AssertableInertia $search) => $search
                    ->hasAll('id', 'criteria', 'property_type_label', 'created_at')
                )
            );
    }

    public function test_a_user_can_save_a_search(): void
    {
        $user = User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);

        $this->actingAs($user)
            ->post('/saved-searches', [
                'property_type' => 'flat',
                'max_price' => '300000',
                'min_bedrooms' => '2',
                'region' => 'Leeds',
            ])
            ->assertRedirect('/saved-searches');

        $search = $user->savedSearches()->sole();
        $this->assertSame(PropertyType::Flat, $search->property_type);
        $this->assertSame(300_000, $search->max_price);
        $this->assertSame(2, $search->min_bedrooms);
        $this->assertSame('Leeds', $search->region);
    }

    public function test_blank_criteria_are_saved_as_any(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/saved-searches', ['max_price' => '250000', 'region' => '', 'property_type' => ''])
            ->assertRedirect('/saved-searches');

        $search = $user->savedSearches()->sole();
        $this->assertSame(250_000, $search->max_price);
        $this->assertNull($search->region);
        $this->assertNull($search->property_type);
        $this->assertNull($search->min_bedrooms);
    }

    /**
     * A search with no criteria would alert on every new listing.
     */
    public function test_a_search_needs_at_least_one_criterion(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/saved-searches', ['max_price' => '', 'region' => ''])
            ->assertSessionHasErrors('criteria');

        $this->assertDatabaseCount('saved_searches', 0);
    }

    public function test_a_search_uses_the_same_validation_as_the_listings_filter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/saved-searches', ['property_type' => 'castle', 'max_price' => '-1', 'min_bedrooms' => '99'])
            ->assertSessionHasErrors(['property_type', 'max_price', 'min_bedrooms']);
    }

    /**
     * Otherwise a typo'd region is saved and the alert silently never fires.
     */
    public function test_a_search_rejects_a_region_no_branch_covers(): void
    {
        $user = User::factory()->create();
        Branch::factory()->create(['region' => 'Leeds']);

        $this->actingAs($user)
            ->post('/saved-searches', ['region' => 'Atlantis'])
            ->assertSessionHasErrors('region');
    }

    public function test_a_user_can_delete_their_saved_search(): void
    {
        $search = SavedSearch::factory()->create(['max_price' => 300_000]);

        $this->actingAs($search->user)
            ->delete("/saved-searches/{$search->id}")
            ->assertRedirect('/saved-searches');

        $this->assertModelMissing($search);
    }

    /**
     * Not found, not forbidden, so ids don't reveal which searches exist.
     */
    public function test_a_user_cannot_delete_someone_elses_saved_search(): void
    {
        $search = SavedSearch::factory()->create(['max_price' => 300_000]);

        $this->actingAs(User::factory()->create())
            ->delete("/saved-searches/{$search->id}")
            ->assertNotFound();

        $this->assertModelExists($search);
    }

    /**
     * With the auth stub, a guest only happens on an unseeded database. There
     * is no login page, so they're sent to the public listings.
     */
    public function test_guests_are_sent_to_the_listings(): void
    {
        $this->get('/saved-searches')->assertRedirect('/');
        $this->post('/saved-searches', ['max_price' => '300000'])->assertRedirect('/');
    }
}
