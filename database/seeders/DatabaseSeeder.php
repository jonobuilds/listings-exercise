<?php

namespace Database\Seeders;

use App\Actions\PublishListing;
use App\Enums\ListingStatus;
use App\Models\Branch;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(PublishListing $publishListing): void
    {
        // The demo user that the auth stub resolves every request as.
        $demoUser = User::factory()->create([
            'name' => 'Demo User',
            'email' => 'demo@street.example',
        ]);

        $branches = Branch::factory(8)->create();

        foreach ($branches as $branch) {
            Listing::factory(rand(18, 30))->live()->for($branch)->create();
            Listing::factory(rand(3, 6))->underOffer()->for($branch)->create();
            Listing::factory(rand(4, 8))->sold()->for($branch)->create();
            Listing::factory(rand(2, 5))->for($branch)->create(); // drafts
        }

        // A saved search for the demo user, then two of its region's drafts
        // published through the real path, so the alerts page has something
        // to show and the remaining drafts are there to publish by hand.
        $region = $branches->firstOrFail()->region;
        $demoUser->savedSearches()->create(['region' => $region]);

        Listing::query()
            ->where('status', ListingStatus::Draft)
            ->whereRelation('branch', 'region', $region)
            ->orderBy('id')
            ->limit(2)
            ->get()
            ->each(fn (Listing $listing) => $publishListing->handle($listing));
    }
}
