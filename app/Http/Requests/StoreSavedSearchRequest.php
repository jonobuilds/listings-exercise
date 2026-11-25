<?php

namespace App\Http\Requests;

use App\Data\ListingCriteria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSavedSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same criteria as the listings filter, so any search can be saved.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = ListingCriteria::rules();

        // A region nobody covers would never match, so the alert would
        // silently never fire. The listings filter can stay lenient; a
        // saved search can't.
        $rules['region'][] = Rule::exists('branches', 'region');

        return $rules;
    }

    /**
     * A search with no criteria matches every new listing. That's the "too
     * many emails" Support warned about, so at least one is required.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $given = array_filter(
                    $this->only(array_keys(ListingCriteria::rules())),
                    fn ($value) => filled($value),
                );

                if ($given === []) {
                    $validator->errors()->add('criteria', 'Choose at least one filter to save a search.');
                }
            },
        ];
    }

    public function criteria(): ListingCriteria
    {
        return ListingCriteria::fromArray($this->validated());
    }
}
