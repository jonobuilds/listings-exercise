<?php

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Models\ListingAlert;
use App\Models\SavedSearch;
use Illuminate\Database\Eloquent\Collection;

/**
 * Alert every user with a saved search the listing matches.
 *
 * Safe to run more than once for the same listing: the unique index on
 * (user_id, listing_id) means a user already alerted is skipped, so a user
 * with several matching searches, or a listing that goes live again, still
 * gets one alert.
 */
class GenerateAlertsForListing
{
    private const CHUNK_SIZE = 500;

    /**
     * @return int The number of new alerts created.
     */
    public function handle(Listing $listing): int
    {
        // Only listings on the market are worth an alert. Guarding here keeps
        // this safe to call from anywhere, not just the ListingWentLive hook.
        if ($listing->status !== ListingStatus::Live) {
            return 0;
        }

        $listing->loadMissing('branch');
        $created = 0;
        $now = now();

        SavedSearch::query()
            ->matchingListing($listing)
            ->select(['id', 'user_id'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $searches) use ($listing, $now, &$created) {
                $rows = $searches
                    ->unique('user_id')
                    ->map(fn (SavedSearch $search) => [
                        'user_id' => $search->user_id,
                        'listing_id' => $listing->id,
                        'saved_search_id' => $search->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->values()
                    ->all();

                $created += ListingAlert::query()->insertOrIgnore($rows);
            });

        return $created;
    }
}
