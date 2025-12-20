<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\DiscountCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountCodeController extends Controller
{
    use ApiResponseTrait;

    /**
     * Validate a discount code (public endpoint for cart).
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $code = strtoupper(trim($request->code));
        $discountCode = DiscountCode::where('code', $code)->first();

        if (! $discountCode) {
            return response()->json([
                'success' => false,
                'message' => 'كود الخصم غير صالح',
                'valid' => false,
            ], 404);
        }

        if (! $discountCode->isValid()) {
            $message = 'كود الخصم غير صالح';

            if (! $discountCode->is_active) {
                $message = 'كود الخصم غير مفعل';
            } elseif ($discountCode->valid_until && now()->toDateString() > $discountCode->valid_until->toDateString()) {
                $message = 'انتهت صلاحية كود الخصم';
            } elseif ($discountCode->valid_from && now()->toDateString() < $discountCode->valid_from->toDateString()) {
                $message = 'كود الخصم غير ساري بعد';
            } elseif ($discountCode->usage_limit && $discountCode->times_used >= $discountCode->usage_limit) {
                $message = 'تم استنفاد كود الخصم';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'valid' => false,
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'كود الخصم صالح',
            'valid' => true,
            'data' => [
                'code' => $discountCode->code,
                'discount_percent' => $discountCode->discount_percent,
            ],
        ]);
    }

    /**
     * Get all discount codes (admin only).
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $query = DiscountCode::query()->orderBy('created_at', 'desc');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $codes = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $codes,
        ]);
    }

    /**
     * Get a single discount code (admin only).
     */
    public function show(Request $request, DiscountCode $discountCode): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        return response()->json([
            'success' => true,
            'data' => $discountCode,
        ]);
    }

    /**
     * Create a new discount code (admin only).
     */
    public function store(Request $request): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:discount_codes,code',
            'discount_percent' => 'required|integer|min:1|max:100',
            'is_active' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));

        $discountCode = DiscountCode::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم إنشاء كود الخصم بنجاح',
            'data' => $discountCode,
        ], 201);
    }

    /**
     * Update a discount code (admin only).
     */
    public function update(Request $request, DiscountCode $discountCode): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $validated = $request->validate([
            'code' => 'sometimes|string|max:50|unique:discount_codes,code,'.$discountCode->id,
            'discount_percent' => 'sometimes|integer|min:1|max:100',
            'is_active' => 'sometimes|boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $discountCode->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث كود الخصم بنجاح',
            'data' => $discountCode,
        ]);
    }

    /**
     * Toggle discount code active status (admin only).
     */
    public function toggle(Request $request, DiscountCode $discountCode): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $discountCode->update(['is_active' => ! $discountCode->is_active]);

        return response()->json([
            'success' => true,
            'message' => $discountCode->is_active ? 'تم تفعيل كود الخصم' : 'تم إلغاء تفعيل كود الخصم',
            'data' => $discountCode,
        ]);
    }

    /**
     * Delete a discount code (admin only).
     */
    public function destroy(Request $request, DiscountCode $discountCode): JsonResponse
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Admin access required');
        }

        $discountCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف كود الخصم بنجاح',
        ]);
    }
}
