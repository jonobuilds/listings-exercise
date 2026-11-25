<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedSearchRequest;
use App\Http\Resources\SavedSearchResource;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Behind the `auth` middleware, so #[CurrentUser] is never null here.
 */
class SavedSearchController extends Controller
{
    public function index(#[CurrentUser] User $user): Response
    {
        $savedSearches = $user
            ->savedSearches()
            ->latest()
            ->orderByDesc('id')
            ->get();

        return Inertia::render('SavedSearches/Index', [
            'savedSearches' => SavedSearchResource::collection($savedSearches),
        ]);
    }

    /**
     * Saving doesn't backfill alerts for listings that already match: the
     * user is saving from those very results. Alerts are for what's new.
     */
    public function store(StoreSavedSearchRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        $user->savedSearches()->create($request->criteria()->toArray());

        return redirect()->route('saved-searches.index');
    }

    /**
     * Ownership is checked by SavedSearchPolicy on the route.
     */
    public function destroy(SavedSearch $savedSearch): RedirectResponse
    {
        $savedSearch->delete();

        return redirect()->route('saved-searches.index');
    }
}
