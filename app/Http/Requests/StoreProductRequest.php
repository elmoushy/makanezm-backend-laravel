<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title_ar' => ['required', 'string', 'max:255'],
            'title_en' => ['required', 'string', 'max:255'],
            'description_ar' => ['nullable', 'string'],
            'description_en' => ['nullable', 'string'],
            'type' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'in_stock' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'main_image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],

            // Payment options (array of options - only type and label, no months/percentage)
            'payment_options' => ['nullable', 'array'],
            'payment_options.*.type' => ['required', 'in:cash,installment,wallet'],
            'payment_options.*.label' => ['nullable', 'string', 'max:100'],
            'payment_options.*.is_active' => ['nullable', 'boolean'],

            // Resale plans (array of plans)
            'resale_plans' => ['nullable', 'array'],
            'resale_plans.*.months' => ['required', 'integer', 'min:1'],
            'resale_plans.*.profit_percentage' => ['required', 'numeric', 'min:0', 'max:200'],
            'resale_plans.*.label' => ['nullable', 'string', 'max:100'],
            'resale_plans.*.is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Product title is required.',
            'type.required' => 'Product type is required.',
            'price.required' => 'Product price is required.',
            'price.min' => 'Product price must be at least 0.',
            'stock_quantity.min' => 'Stock quantity cannot be negative.',
            'main_image.required' => 'Main product image is required.',
            'main_image.image' => 'Main image must be an image file.',
            'main_image.max' => 'Main image size must not exceed 2MB.',
            'resale_plans.*.months.required' => 'Resale plan months are required.',
            'resale_plans.*.profit_percentage.required' => 'Resale plan profit percentage is required.',
        ];
    }
}
