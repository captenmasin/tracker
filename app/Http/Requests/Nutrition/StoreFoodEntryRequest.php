<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFoodEntryRequest extends FormRequest
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
            'food_id' => [
                'nullable',
                Rule::exists('foods', 'id')->where(fn ($query) => $query->where('user_id', $this->user()?->id)),
            ],
            'name' => ['required_without:food_id', 'string', 'max:191'],
            'barcode' => ['nullable', 'string', 'max:32'],
            'consumed_on' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'serving_size_value' => ['nullable', 'numeric', 'min:0'],
            'serving_unit' => ['nullable', 'string', 'max:32'],
            'serving_unit_raw' => ['nullable', 'string', 'max:16'],
            'calories' => ['required_without:food_id', 'numeric', 'min:0'],
            'protein_grams' => ['required_without:food_id', 'numeric', 'min:0'],
            'carb_grams' => ['required_without:food_id', 'numeric', 'min:0'],
            'fat_grams' => ['required_without:food_id', 'numeric', 'min:0'],
            'source' => ['nullable', 'string', Rule::in(['food', 'manual'])],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $servingUnit = $this->input('serving_unit');
        $servingUnitRaw = $this->input('serving_unit_raw');

        $this->merge([
            'consumed_on' => $this->input('consumed_on') ?: now()->toDateString(),
            'serving_unit' => $servingUnit ?: 'g',
            'serving_unit_raw' => $servingUnitRaw ?: ($servingUnit ?: 'g'),
            'serving_size_value' => $this->input('serving_size_value'),
        ]);
    }
}
