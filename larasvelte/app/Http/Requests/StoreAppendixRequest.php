<?php

namespace App\Http\Requests;

use App\Models\Appendix;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAppendixRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Appendix::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'platform' => [
                'required',
                'string',
                'max:50',
                Rule::unique('appendices', 'platform')->where('user_id', $this->user()->id),
            ],
            'content' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->user()->appendices()->count() >= 4) {
                    $validator->errors()->add('platform', 'You can create a maximum of 4 platforms.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'platform' => trim((string) $this->input('platform')),
            'content' => trim((string) $this->input('content')),
        ]);
    }
}
