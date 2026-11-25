<?php

namespace Tests\Feature;

use App\Data\ListingCriteria;
use App\Enums\PropertyType;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\SavedSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Does this listing match this search?" is answered by two queries:
 * Listing::matching() (the listings page: listings for some criteria) and
 * SavedSearch::matchingListing() (alerts: searches for one listing). They
 * must agree, or a user would be alerted about a listing their own search
 * doesn't show, or not alerted about one it does.
 */
class MatchingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_directions_agree_for_every_listing_and_search(): void
    {
        $leeds = Branch::factory()->create(['region' => 'Leeds']);
        $york = Branch::factory()->create(['region' => 'York']);

        // Listings either side of, and exactly on, each search's limits.
        $listings = collect([
            [$leeds, PropertyType::Flat, 250_000, 2],
            [$leeds, PropertyType::Flat, 300_000, 3],
            [$leeds, PropertyType::Detached, 300_001, 4],
            [$york, PropertyType::Flat, 299_999, 1],
            [$york, PropertyType::Terraced, 450_000, 2],
        ])->map(fn (array $row) => Listing::factory()->live()->for($row[0])->create([
            'property_type' => $row[1],
            'price' => $row[2],
            'bedrooms' => $row[3],
        ]));

        $searches = collect([
            ['max_price' => 300_000],
            ['min_price' => 300_000],
            ['min_price' => 250_000, 'max_price' => 300_000],
            ['min_bedrooms' => 2],
            ['property_type' => PropertyType::Flat],
            ['region' => 'Leeds'],
            ['region' => 'York', 'max_price' => 300_000],
            ['property_type' => PropertyType::Flat, 'min_bedrooms' => 3, 'region' => 'Leeds', 'max_price' => 300_000],
            ['region' => 'Bristol'],
        ])->map(fn (array $criteria) => SavedSearch::factory()->create($criteria));

        $matches = 0;

        foreach ($searches as $search) {
            foreach ($listings as $listing) {
                $listingSide = Listing::query()->whereKey($listing->id)->matching($search->criteria())->exists();
                $searchSide = SavedSearch::query()->whereKey($search->id)->matchingListing($listing)->exists();

                $this->assertSame(
                    $listingSide,
                    $searchSide,
                    "Listing {$listing->id} vs search ".json_encode($search->criteria()->toArray()),
                );

                $matches += (int) $listingSide;
            }
        }

        // Guard against the grid being trivially all-match or no-match.
        $this->assertGreaterThan(0, $matches);
        $this->assertLessThan($searches->count() * $listings->count(), $matches);
    }

    public function test_a_search_with_no_criteria_matches_everything(): void
    {
        $listing = Listing::factory()->live()->create();
        $search = SavedSearch::factory()->create();

        $this->assertTrue($search->criteria()->isEmpty());
        $this->assertTrue(Listing::query()->matching(new ListingCriteria)->whereKey($listing->id)->exists());
        $this->assertTrue(SavedSearch::query()->matchingListing($listing)->whereKey($search->id)->exists());
    }
}
