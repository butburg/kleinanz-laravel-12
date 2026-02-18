<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdImageRequest extends FormRequest
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
            'images' => ['required', 'array', 'min:1', 'max:'.config('ads.image.max_files')],
            'images.*' => [
                'required',
                'file',
                'mimes:'.implode(',', config('ads.image.supported_formats')),
                'max:'.config('ads.image.max_file_kb'),
            ],
        ];
    }
}
