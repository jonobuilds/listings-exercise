<?php

namespace App\Http\Requests;

use App\Data\ListingCriteria;
use Illuminate\Foundation\Http\FormRequest;

class ListingIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...ListingCriteria::rules(),
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * The search criteria. Paging is not a criterion, so it's left out.
     */
    public function criteria(): ListingCriteria
    {
        return ListingCriteria::fromArray($this->validated());
    }
}
