<?php

namespace Tests\Feature;

use App\Actions\GenerateAlertsForListing;
use App\Actions\PublishListing;
use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Events\ListingWentLive;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingAlertTest extends TestCase
{
    use RefreshDatabase;

    private Branch $leeds;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leeds = Branch::factory()->create(['region' => 'Leeds']);
        Branch::factory()->create(['region' => 'York']);
    }

    /**
     * A draft flat in Leeds: £300,000, 2 bedrooms.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function draftListing(array $attributes = []): Listing
    {
        return Listing::factory()->draft()->for($this->leeds)->create([
            'property_type' => PropertyType::Flat,
            'price' => 300_000,
            'bedrooms' => 2,
            ...$attributes,
        ]);
    }

    private function publish(Listing $listing): void
    {
        app(PublishListing::class)->handle($listing);
    }

    public function test_publishing_a_matching_listing_alerts_the_searcher(): void
    {
        $search = SavedSearch::factory()->create(['region' => 'Leeds', 'max_price' => 350_000]);
        $listing = $this->draftListing();

        $this->publish($listing);

        $alert = ListingAlert::query()->sole();
        $this->assertSame($search->user_id, $alert->user_id);
        $this->assertSame($listing->id, $alert->listing_id);
        $this->assertSame($search->id, $alert->saved_search_id);
    }

    public function test_a_listing_created_as_live_alerts_the_searcher(): void
    {
        SavedSearch::factory()->create(['region' => 'Leeds']);

        Listing::factory()->live()->for($this->leeds)->create();

        $this->assertDatabaseCount('listing_alerts', 1);
    }

    /**
     * @param  array<string, mixed>  $criteria
     */
    #[DataProvider('criteriaTheListingFails')]
    public function test_a_listing_that_misses_any_criterion_raises_no_alert(array $criteria): void
    {
        SavedSearch::factory()->create($criteria);

        $this->publish($this->draftListing());

        $this->assertDatabaseCount('listing_alerts', 0);
    }

    /**
     * Each fails the £300,000, 2-bed Leeds flat on exactly one criterion.
     *
     * @return array<string, array{array<string, mixed>}>
     */
    public static function criteriaTheListingFails(): array
    {
        return [
            'wrong type' => [['property_type' => PropertyType::Detached, 'region' => 'Leeds']],
            'under the minimum price' => [['min_price' => 300_001, 'region' => 'Leeds']],
            'over budget' => [['max_price' => 299_999, 'region' => 'Leeds']],
            'too few bedrooms' => [['min_bedrooms' => 3, 'region' => 'Leeds']],
            'wrong region' => [['region' => 'York', 'max_price' => 400_000]],
        ];
    }

    public function test_limits_are_inclusive(): void
    {
        SavedSearch::factory()->create(['min_price' => 300_000, 'max_price' => 300_000, 'min_bedrooms' => 2]);

        $this->publish($this->draftListing());

        $this->assertDatabaseCount('listing_alerts', 1);
    }

    public function test_listings_that_are_not_live_raise_no_alert(): void
    {
        SavedSearch::factory()->create(['region' => 'Leeds']);

        $draft = $this->draftListing();
        $draft->update(['price' => 280_000]);
        Listing::factory()->sold()->for($this->leeds)->create();
        Listing::factory()->underOffer()->for($this->leeds)->create();

        $this->assertDatabaseCount('listing_alerts', 0);
    }

    /**
     * Alerts are for listings coming on the market. A price drop on a listing
     * that's already live doesn't make it new, even if it now fits a budget.
     */
    public function test_editing_a_live_listing_raises_no_alert(): void
    {
        $listing = Listing::factory()->live()->for($this->leeds)->create(['price' => 400_000]);
        SavedSearch::factory()->create(['max_price' => 350_000]);

        $listing->update(['price' => 340_000]);

        $this->assertDatabaseCount('listing_alerts', 0);
    }

    public function test_a_user_with_several_matching_searches_gets_one_alert(): void
    {
        $user = User::factory()->create();
        $first = SavedSearch::factory()->for($user)->create(['region' => 'Leeds']);
        SavedSearch::factory()->for($user)->create(['max_price' => 350_000]);

        $this->publish($this->draftListing());

        $alert = ListingAlert::query()->sole();
        $this->assertSame($first->id, $alert->saved_search_id);
    }

    public function test_each_matching_user_gets_their_own_alert(): void
    {
        SavedSearch::factory()->create(['region' => 'Leeds']);
        SavedSearch::factory()->create(['max_price' => 350_000]);
        SavedSearch::factory()->create(['region' => 'York']); // doesn't match

        $this->publish($this->draftListing());

        $this->assertDatabaseCount('listing_alerts', 2);
    }

    /**
     * A sale falling through puts the listing back on the market. Users who
     * were already told about it aren't told again; users who weren't are.
     */
    public function test_a_listing_back_on_the_market_alerts_only_users_not_yet_told(): void
    {
        $alreadyTold = SavedSearch::factory()->create(['region' => 'Leeds']);
        $listing = $this->draftListing();
        $this->publish($listing);

        $listing->update(['status' => ListingStatus::UnderOffer]);
        $newcomer = SavedSearch::factory()->create(['max_price' => 350_000]);
        $this->publish($listing);

        $this->assertSame(1, ListingAlert::query()->where('user_id', $alreadyTold->user_id)->count());
        $this->assertSame(1, ListingAlert::query()->where('user_id', $newcomer->user_id)->count());
    }

    public function test_publishing_keeps_the_original_listing_date_when_relisting(): void
    {
        $listing = Listing::factory()->underOffer()->for($this->leeds)->create();
        $listedAt = $listing->listed_at;

        $this->publish($listing);

        $this->assertSame(ListingStatus::Live, $listing->refresh()->status);
        $this->assertTrue($listedAt->equalTo($listing->listed_at));
    }

    public function test_publishing_a_draft_sets_its_listing_date(): void
    {
        $listing = $this->draftListing();

        $this->publish($listing);

        $this->assertNotNull($listing->refresh()->listed_at);
    }

    /**
     * Alert rows written inside the transaction would roll back with it
     * anyway. What matters is that nothing outside the database (a queued job,
     * an email) hears about a listing that never actually went live, so the
     * event itself must wait for the commit.
     */
    public function test_a_rolled_back_publish_is_never_announced(): void
    {
        $announced = [];
        Event::listen(ListingWentLive::class, function (ListingWentLive $event) use (&$announced) {
            $announced[] = $event->listing->id;
        });
        $rolledBack = $this->draftListing();
        $committed = $this->draftListing();

        DB::beginTransaction();
        $this->publish($rolledBack);
        $this->assertSame([], $announced, 'Announced before the transaction committed.');
        DB::rollBack();

        $this->publish($committed);

        $this->assertSame([$committed->id], $announced);
    }

    public function test_generating_alerts_twice_creates_them_once(): void
    {
        SavedSearch::factory()->create(['region' => 'Leeds']);
        $listing = Listing::factory()->live()->for($this->leeds)->create();
        $generate = app(GenerateAlertsForListing::class);

        $this->assertSame(0, $generate->handle($listing), 'The model hook already alerted.');
        $this->assertDatabaseCount('listing_alerts', 1);
    }

    public function test_generating_alerts_ignores_listings_that_are_not_live(): void
    {
        SavedSearch::factory()->create(['region' => 'Leeds']);

        $this->assertSame(0, app(GenerateAlertsForListing::class)->handle($this->draftListing()));
        $this->assertDatabaseCount('listing_alerts', 0);
    }
}
