<?php

namespace App\Http\Controllers;

use App\Http\Resources\ListingAlertResource;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Inertia\Inertia;
use Inertia\Response;

class ListingAlertController extends Controller
{
    /**
     * The user's alerts, newest first.
     *
     * Only listings still on the market are shown: an alert for one that has
     * since sold would link to a page that 404s.
     *
     * Behind the `auth` middleware, so #[CurrentUser] is never null here.
     */
    public function index(#[CurrentUser] User $user): Response
    {
        $alerts = $user
            ->listingAlerts()
            ->whereIn('listing_id', Listing::query()->live()->select('id'))
            ->with(['listing.branch', 'savedSearch'])
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return Inertia::render('Alerts/Index', [
            'alerts' => ListingAlertResource::collection($alerts),
        ]);
    }
}
