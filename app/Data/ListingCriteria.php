<?php

namespace App\Data;

use App\Enums\PropertyType;
use Illuminate\Validation\Rules\Enum;

/**
 * The criteria a listing can be searched by: the listings page filters and a
 * saved search are both one of these.
 *
 * A null criterion means "any". Limits are inclusive: a listing at exactly
 * maxPrice, or with exactly minBedrooms, matches.
 *
 * Adding a criterion means adding it here, to Listing::scopeMatching() and
 * SavedSearch::scopeMatchingListing(), and as a saved_searches column.
 * MatchingConsistencyTest fails if the two scopes disagree.
 */
final readonly class ListingCriteria
{
    public function __construct(
        public ?PropertyType $propertyType = null,
        public ?int $maxPrice = null,
        public ?int $minBedrooms = null,
        public ?string $region = null,
    ) {}

    /**
     * Validation rules for the criteria, keyed by input name.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'property_type' => ['nullable', new Enum(PropertyType::class)],
            'max_price' => ['nullable', 'integer', 'min:0'],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'region' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Build from validated input. Blank values mean "any".
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $value = fn (string $key) => filled($input[$key] ?? null) ? $input[$key] : null;

        return new self(
            propertyType: ($type = $value('property_type')) !== null ? PropertyType::from($type) : null,
            maxPrice: ($price = $value('max_price')) !== null ? (int) $price : null,
            minBedrooms: ($bedrooms = $value('min_bedrooms')) !== null ? (int) $bedrooms : null,
            region: ($region = $value('region')) !== null ? (string) $region : null,
        );
    }

    /**
     * Keyed by input name, which is also the saved_searches column name.
     *
     * @return array{property_type: ?string, max_price: ?int, min_bedrooms: ?int, region: ?string}
     */
    public function toArray(): array
    {
        return [
            'property_type' => $this->propertyType?->value,
            'max_price' => $this->maxPrice,
            'min_bedrooms' => $this->minBedrooms,
            'region' => $this->region,
        ];
    }

    /**
     * True when nothing is constrained, so every listing matches.
     */
    public function isEmpty(): bool
    {
        return array_filter($this->toArray(), fn ($value) => $value !== null) === [];
    }
}
