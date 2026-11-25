<?php

namespace App\Listeners;

use App\Actions\GenerateAlertsForListing;
use App\Events\ListingWentLive;

/**
 * Runs synchronously so the feature works without a queue worker (the README
 * runs none). In production this should implement ShouldQueue: matching and
 * inserting alerts shouldn't sit in the request that publishes a listing.
 * GenerateAlertsForListing is idempotent, so a retried job is safe.
 */
class GenerateAlertsForNewListing
{
    public function __construct(private readonly GenerateAlertsForListing $generateAlerts) {}

    public function handle(ListingWentLive $event): void
    {
        $this->generateAlerts->handle($event->listing);
    }
}
