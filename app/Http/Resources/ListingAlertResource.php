<?php

namespace App\Http\Resources;

use App\Models\ListingAlert;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ListingAlert
 */
class ListingAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at->toIso8601String(),
            'listing' => new ListingResource($this->listing),
            // Null once the search that matched has been deleted.
            'saved_search' => $this->savedSearch ? new SavedSearchResource($this->savedSearch) : null,
        ];
    }
}
