<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFoodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'barcode' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('foods', 'barcode')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'serving_size' => ['required', 'numeric', 'min:0.01'],
            'serving_unit' => ['required', 'string', 'max:32'],
            'calories_per_serving' => ['required', 'numeric', 'min:0'],
            'protein_grams' => ['nullable', 'numeric', 'min:0'],
            'carb_grams' => ['nullable', 'numeric', 'min:0'],
            'fat_grams' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'serving_unit' => $this->input('serving_unit') ?: 'serving',
        ]);
    }
}
