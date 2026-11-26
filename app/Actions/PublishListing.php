<?php

namespace App\Actions;

use App\Enums\ListingStatus;
use App\Models\Listing;

/**
 * Put a listing on the market.
 *
 * The app had no way to do this before saved searches needed one. Alerts
 * aren't generated here but by Listing's model hook, so any path to Live
 * (this, an import, a future admin screen) alerts the same way.
 */
class PublishListing
{
    public function handle(Listing $listing): Listing
    {
        if ($listing->status === ListingStatus::Live) {
            return $listing;
        }

        $listing->status = ListingStatus::Live;

        // A listing coming back on the market (e.g. a sale fell through)
        // keeps its original listing date.
        $listing->listed_at ??= now();

        $listing->save();

        return $listing;
    }
}
