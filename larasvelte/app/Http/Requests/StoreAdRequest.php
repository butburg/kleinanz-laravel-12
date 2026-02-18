<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:' . config('ads.validation.title_max_length')],
            'description' => [
                'required',
                'string',
                'min:' . config('ads.validation.description_min_length'),
                'max:' . config('ads.validation.description_max_length'),
            ],
            'price' => ['required', 'integer', 'min:0'],
            'condition' => ['required', Rule::in(config('ads.validation.conditions'))],
            'shipping' => ['required', Rule::in(config('ads.validation.shipping_options'))],
            'status' => ['nullable', Rule::in(config('ads.status.options'))],
            'prompt_text' => ['nullable', 'string'],
        ];
    }
}
