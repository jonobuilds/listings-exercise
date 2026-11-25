<?php

namespace App\Models;

use App\Data\ListingCriteria;
use App\Enums\ListingStatus;
use App\Enums\PropertyType;
use App\Events\ListingWentLive;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The casts() method tells Eloquent how to hydrate these attributes, but static
 * analysis can't infer that from a string map — these annotations do.
 *
 * @property int $id
 * @property int $branch_id
 * @property string $reference
 * @property string $address_line_1
 * @property string $city
 * @property string $postcode
 * @property int $price
 * @property int $bedrooms
 * @property int $bathrooms
 * @property PropertyType $property_type
 * @property ListingStatus $status
 * @property Carbon|null $listed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Branch $branch
 */
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'reference',
        'address_line_1',
        'city',
        'postcode',
        'price',
        'bedrooms',
        'bathrooms',
        'property_type',
        'status',
        'listed_at',
    ];

    /**
     * Announce a listing coming on the market, however it got there: created
     * as live, or moved to live from draft, under offer or sold.
     *
     * Bulk query updates (Listing::query()->update(...)) skip model events,
     * so they don't raise this and don't generate alerts.
     */
    protected static function booted(): void
    {
        static::created(function (Listing $listing) {
            if ($listing->status === ListingStatus::Live) {
                ListingWentLive::dispatch($listing);
            }
        });

        static::updated(function (Listing $listing) {
            if ($listing->wasChanged('status') && $listing->status === ListingStatus::Live) {
                ListingWentLive::dispatch($listing);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'property_type' => PropertyType::class,
            'status' => ListingStatus::class,
            'listed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Only listings that are currently on the market.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where('status', ListingStatus::Live);
    }

    /**
     * Listings that meet the given criteria. Null criteria are ignored.
     *
     * SavedSearch::scopeMatchingListing() asks the same question the other
     * way round; keep the two in step.
     *
     * @param  Builder<Listing>  $query
     */
    public function scopeMatching(Builder $query, ListingCriteria $criteria): void
    {
        if ($criteria->propertyType !== null) {
            $query->where('property_type', $criteria->propertyType);
        }

        if ($criteria->maxPrice !== null) {
            $query->where('price', '<=', $criteria->maxPrice);
        }

        if ($criteria->minBedrooms !== null) {
            $query->where('bedrooms', '>=', $criteria->minBedrooms);
        }

        if ($criteria->region !== null) {
            $query->whereHas('branch', fn (Builder $branch) => $branch->where('region', $criteria->region));
        }
    }
}
