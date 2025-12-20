<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
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
        $rules = [
            'type' => ['required', 'in:sale,resale'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['sometimes', 'string', 'max:1000'],
        ];

        // Shipping info required for sale orders
        if ($this->input('type') === 'sale') {
            $rules['shipping_name'] = ['required', 'string', 'max:255'];
            $rules['shipping_phone'] = ['required', 'string', 'max:20'];
            $rules['shipping_city'] = ['required', 'string', 'max:100'];
            $rules['shipping_address'] = ['required', 'string', 'max:500'];
        }

        // Resale plan required for resale orders
        if ($this->input('type') === 'resale') {
            $rules['items.*.resale_plan_id'] = ['required', 'integer', 'exists:product_resale_plans,id'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Order type is required.',
            'type.in' => 'Order type must be sale or resale.',
            'items.required' => 'At least one item is required.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.exists' => 'Product not found.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.resale_plan_id.required' => 'Resale plan is required for resale orders.',
            'items.*.resale_plan_id.exists' => 'Resale plan not found.',
            'shipping_name.required' => 'Shipping name is required for sale orders.',
            'shipping_phone.required' => 'Shipping phone is required for sale orders.',
            'shipping_city.required' => 'Shipping city is required for sale orders.',
            'shipping_address.required' => 'Shipping address is required for sale orders.',
        ];
    }
}
