<?php

namespace App\Http\Requests\Nutrition;

use Illuminate\Foundation\Http\FormRequest;

class StoreCalorieBurnEntryRequest extends FormRequest
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
            'calories' => ['required', 'integer', 'min:1', 'max:20000'],
            'recorded_on' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:191'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'recorded_on' => $this->input('recorded_on') ?: now()->toDateString(),
        ]);
    }
}
