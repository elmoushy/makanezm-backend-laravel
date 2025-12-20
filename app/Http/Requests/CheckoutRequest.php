<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * CheckoutRequest
 *
 * Validates checkout data for both wallet (direct purchase) and resale (investment) items.
 * Ensures all required shipping info for wallet items and validates resale plan selections.
 */
class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Auth handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            // Cart items array
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.purchase_type' => ['required', 'in:wallet,resale'],

            // Company selection (required for each item)
            'items.*.company_id' => ['required', 'integer', 'exists:companies,id'],

            // Resale plan (required when purchase_type is resale)
            'items.*.resale_plan_id' => ['required_if:items.*.purchase_type,resale', 'nullable', 'integer', 'exists:product_resale_plans,id'],

            // Discount code (optional)
            'discount_code' => ['nullable', 'string', 'max:50'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];

        // Check if any item has wallet purchase type (requires shipping)
        $hasWalletItems = collect($this->input('items', []))
            ->contains(fn ($item) => ($item['purchase_type'] ?? '') === 'wallet');

        if ($hasWalletItems) {
            // Phone and address are required - name and city come from user profile
            $rules = array_merge($rules, [
                'shipping_name' => ['nullable', 'string', 'max:255'], // Optional, will use user name
                'shipping_phone' => ['required', 'string', 'max:20'],
                'shipping_phones' => ['nullable', 'array'], // Additional phone numbers
                'shipping_phones.*' => ['string', 'max:20'],
                'shipping_city' => ['nullable', 'string', 'max:100'], // Optional, will use user city
                'shipping_address' => ['required', 'string', 'max:500'],
            ]);
        } else {
            // Shipping is optional for pure investment orders
            $rules = array_merge($rules, [
                'shipping_name' => ['nullable', 'string', 'max:255'],
                'shipping_phone' => ['nullable', 'string', 'max:20'],
                'shipping_phones' => ['nullable', 'array'],
                'shipping_phones.*' => ['string', 'max:20'],
                'shipping_city' => ['nullable', 'string', 'max:100'],
                'shipping_address' => ['nullable', 'string', 'max:500'],
            ]);
        }

        return $rules;
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Cart cannot be empty.',
            'items.min' => 'At least one item is required for checkout.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.exists' => 'One or more products do not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.purchase_type.required' => 'Purchase type must be specified for each item.',
            'items.*.purchase_type.in' => 'Purchase type must be either wallet or resale.',
            'items.*.company_id.required' => 'Company must be selected for each item.',
            'items.*.company_id.exists' => 'Selected company does not exist.',
            'items.*.resale_plan_id.required_if' => 'Resale plan must be selected for investment items.',
            'items.*.resale_plan_id.exists' => 'Selected resale plan does not exist.',
            'shipping_name.required' => 'Shipping name is required for direct purchase items.',
            'shipping_phone.required' => 'Shipping phone is required for direct purchase items.',
            'shipping_city.required' => 'Shipping city is required for direct purchase items.',
            'shipping_address.required' => 'Shipping address is required for direct purchase items.',
        ];
    }

    /**
     * Check if checkout has any wallet (direct purchase) items.
     */
    public function hasWalletItems(): bool
    {
        return collect($this->input('items', []))
            ->contains(fn ($item) => ($item['purchase_type'] ?? '') === 'wallet');
    }

    /**
     * Check if checkout has any resale (investment) items.
     */
    public function hasResaleItems(): bool
    {
        return collect($this->input('items', []))
            ->contains(fn ($item) => ($item['purchase_type'] ?? '') === 'resale');
    }

    /**
     * Check if checkout is purely investment (no shipping needed).
     */
    public function isPureInvestment(): bool
    {
        return ! $this->hasWalletItems() && $this->hasResaleItems();
    }
}
