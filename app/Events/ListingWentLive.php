<?php

namespace App\Events;

use App\Models\Listing;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A listing has just come on the market: created as live, or moved to live
 * from another status.
 *
 * Dispatched after the surrounding transaction commits, so a rolled-back save
 * never produces alerts.
 */
class ListingWentLive implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Listing $listing) {}
}
