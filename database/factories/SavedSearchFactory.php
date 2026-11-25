<?php

namespace Database\Factories;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSearch>
 */
class SavedSearchFactory extends Factory
{
    protected $model = SavedSearch::class;

    /**
     * Every criterion is "any" by default, so a test states exactly the
     * criteria it depends on.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'property_type' => null,
            'min_price' => null,
            'max_price' => null,
            'min_bedrooms' => null,
            'region' => null,
        ];
    }
}
