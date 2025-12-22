<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHeroProductsCoverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        // Allow either a file upload or a data-URI/base64 string.
        // Size limits are enforced in withValidator() based on the actual input type.
        return [
            'products_cover_image' => ['required'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('products_cover_image')) {
                $fileValidator = validator($this->all(), [
                    'products_cover_image' => ['file', 'image', 'max:4096'], // 4MB (Safe for default MySQL packet size)
                ]);
                if ($fileValidator->fails()) {
                    foreach ($fileValidator->errors()->all() as $message) {
                        $validator->errors()->add('products_cover_image', $message);
                    }
                }

                return;
            }

            // Base64 string validation
            // 4MB binary ~= 5.3MB Base64. We limit string to ~5MB to be safe.
            $stringValidator = validator($this->all(), [
                'products_cover_image' => ['string', 'max:5500000'],
            ]);

            if ($stringValidator->fails()) {
                foreach ($stringValidator->errors()->all() as $message) {
                    $validator->errors()->add('products_cover_image', $message);
                }
            }
        });
    }
}
