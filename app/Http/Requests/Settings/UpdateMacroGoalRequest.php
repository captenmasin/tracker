<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateMacroGoalRequest extends FormRequest
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
            'daily_calorie_goal' => ['required', 'integer', 'min:800', 'max:15000'],
            'protein_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'carb_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'fat_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $total = (float) $this->input('protein_percentage', 0)
                + (float) $this->input('carb_percentage', 0)
                + (float) $this->input('fat_percentage', 0);

            if (abs($total - 100) > 0.1) {
                $validator->errors()->add('protein_percentage', 'The macro percentages must add up to 100%.');
            }
        });
    }
}
