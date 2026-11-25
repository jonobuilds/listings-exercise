<?php

namespace App\Models;

use Database\Factories\ListingAlertFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tells a user that a listing matching one of their saved searches went live.
 * There's at most one per user per listing.
 *
 * @property int $id
 * @property int $user_id
 * @property int $listing_id
 * @property int|null $saved_search_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 * @property-read Listing $listing
 * @property-read SavedSearch|null $savedSearch
 */
class ListingAlert extends Model
{
    /** @use HasFactory<ListingAlertFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'listing_id',
        'saved_search_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Listing, $this>
     */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /**
     * @return BelongsTo<SavedSearch, $this>
     */
    public function savedSearch(): BelongsTo
    {
        return $this->belongsTo(SavedSearch::class);
    }
}
