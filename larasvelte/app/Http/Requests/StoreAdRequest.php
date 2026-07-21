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
        $isGenerateFlow = $this->boolean('_generate');

        $titleRules = ['string', 'max:' . config('ads.validation.title_max_length')];
        $descriptionRules = ['string', 'max:' . config('ads.validation.description_max_length')];
        $priceRules = ['integer', 'min:0'];
        $conditionRules = [Rule::in(config('ads.validation.conditions'))];
        $shippingRules = [Rule::in(config('ads.validation.shipping_options'))];

        if ($isGenerateFlow) {
            array_unshift($titleRules, 'nullable');
            array_unshift($descriptionRules, 'nullable');
            array_unshift($priceRules, 'nullable');
            array_unshift($conditionRules, 'nullable');
            array_unshift($shippingRules, 'nullable');
        } else {
            array_unshift($titleRules, 'required');
            array_unshift($descriptionRules, 'required', 'min:' . config('ads.validation.description_min_length'));
            array_unshift($priceRules, 'required');
            array_unshift($conditionRules, 'required');
            array_unshift($shippingRules, 'required');
        }

        return [
            'title' => $titleRules,
            'description' => $descriptionRules,
            'price' => $priceRules,
            'condition' => $conditionRules,
            'shipping' => $shippingRules,
            'status' => ['nullable', Rule::in(config('ads.status.options'))],
            'prompt_text' => ['nullable', 'string', 'max:' . config('ads.validation.prompt_max_length')],
            'auto_crop_enabled' => ['sometimes', 'boolean'],
            'images' => [Rule::requiredIf($isGenerateFlow), 'array', 'max:' . config('ads.image.max_files')],
            'images.*' => [
                'file',
                'mimes:' . implode(',', config('ads.image.supported_formats')),
                'max:' . config('ads.image.max_file_kb'),
            ],
            'title_image_index' => [
                'nullable',
                'integer',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    /** @var list<\Illuminate\Http\UploadedFile> $images */
                    $images = $this->file('images', []);
                    if (! array_key_exists((int) $value, $images)) {
                        $fail('The selected title image is invalid.');
                    }
                },
            ],
        ];
    }
}
