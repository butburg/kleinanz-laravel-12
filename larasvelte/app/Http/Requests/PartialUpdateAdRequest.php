<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartialUpdateAdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request for partial updates.
     * Each field is optional, but if provided, must pass validation.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:' . config('ads.validation.title_max_length')],
            'description' => [
                'sometimes',
                'string',
                'min:' . config('ads.validation.description_min_length'),
                'max:' . config('ads.validation.description_max_length'),
            ],
            'price' => ['sometimes', 'integer', 'min:0'],
            'condition' => ['sometimes', Rule::in(config('ads.validation.conditions'))],
            'shipping' => ['sometimes', Rule::in(config('ads.validation.shipping_options'))],
            'status' => ['sometimes', Rule::in(config('ads.status.options'))],
            'prompt_text' => ['sometimes', 'nullable', 'string', 'max:' . config('ads.validation.prompt_max_length')],
        ];
    }
}
