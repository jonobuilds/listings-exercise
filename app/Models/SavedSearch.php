<?php

namespace App\Models;

use App\Data\ListingCriteria;
use App\Enums\PropertyType;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user's stored ListingCriteria. Null criteria mean "any".
 *
 * @property int $id
 * @property int $user_id
 * @property PropertyType|null $property_type
 * @property int|null $min_price
 * @property int|null $max_price
 * @property int|null $min_bedrooms
 * @property string|null $region
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    protected $fillable = [
        'property_type',
        'min_price',
        'max_price',
        'min_bedrooms',
        'region',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'property_type' => PropertyType::class,
            'min_price' => 'integer',
            'max_price' => 'integer',
            'min_bedrooms' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function criteria(): ListingCriteria
    {
        return new ListingCriteria(
            propertyType: $this->property_type,
            minPrice: $this->min_price,
            maxPrice: $this->max_price,
            minBedrooms: $this->min_bedrooms,
            region: $this->region,
        );
    }

    /**
     * Saved searches that the given listing satisfies: the reverse of
     * Listing::scopeMatching(), answered in one query rather than by testing
     * every saved search in PHP. Keep the two in step.
     *
     * @param  Builder<SavedSearch>  $query
     */
    public function scopeMatchingListing(Builder $query, Listing $listing): void
    {
        $query
            ->where(fn (Builder $q) => $q->whereNull('property_type')->orWhere('property_type', $listing->property_type))
            ->where(fn (Builder $q) => $q->whereNull('min_price')->orWhere('min_price', '<=', $listing->price))
            ->where(fn (Builder $q) => $q->whereNull('max_price')->orWhere('max_price', '>=', $listing->price))
            ->where(fn (Builder $q) => $q->whereNull('min_bedrooms')->orWhere('min_bedrooms', '<=', $listing->bedrooms))
            ->where(fn (Builder $q) => $q->whereNull('region')->orWhere('region', $listing->branch->region));
    }
}
