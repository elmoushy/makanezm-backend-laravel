<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\Request;

class UserMobileController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all mobile numbers for the authenticated user.
     * GET /api/v1/me/mobiles
     */
    public function index(Request $request)
    {
        $mobiles = $request->user()->mobiles()->orderByDesc('is_primary')->get();

        return $this->successResponse($mobiles, 'Mobiles retrieved successfully');
    }

    /**
     * Add a new mobile number for the authenticated user.
     * POST /api/v1/me/mobiles
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'mobile' => 'required|string|max:20',
            'label' => 'nullable|string|max:50',
            'is_primary' => 'boolean',
        ]);

        $user = $request->user();

        // Check if mobile already exists for this user
        $exists = $user->mobiles()->where('mobile', $validated['mobile'])->exists();
        if ($exists) {
            return $this->errorResponse('This mobile number is already added', 'DUPLICATE', 422);
        }

        // If this is the first mobile or is_primary is true, set as primary
        $isPrimary = $validated['is_primary'] ?? false;
        if ($user->mobiles()->count() === 0) {
            $isPrimary = true;
        }

        $mobile = $user->mobiles()->create([
            'mobile' => $validated['mobile'],
            'label' => $validated['label'] ?? null,
            'is_primary' => $isPrimary,
        ]);

        // If set as primary, unset others
        if ($isPrimary) {
            $mobile->setAsPrimary();
        }

        return $this->createdResponse($mobile, 'Mobile added successfully');
    }

    /**
     * Get a specific mobile number.
     * GET /api/v1/me/mobiles/{id}
     */
    public function show(Request $request, $id)
    {
        $mobile = $request->user()->mobiles()->find($id);

        if (! $mobile) {
            return $this->notFoundResponse('Mobile not found');
        }

        return $this->successResponse($mobile, 'Mobile retrieved successfully');
    }

    /**
     * Update a mobile number.
     * PUT/PATCH /api/v1/me/mobiles/{id}
     */
    public function update(Request $request, $id)
    {
        $mobile = $request->user()->mobiles()->find($id);

        if (! $mobile) {
            return $this->notFoundResponse('Mobile not found');
        }

        $validated = $request->validate([
            'mobile' => 'sometimes|required|string|max:20',
            'label' => 'nullable|string|max:50',
            'is_primary' => 'boolean',
        ]);

        // Check for duplicate if mobile is being changed
        if (isset($validated['mobile']) && $validated['mobile'] !== $mobile->mobile) {
            $exists = $request->user()->mobiles()
                ->where('mobile', $validated['mobile'])
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return $this->errorResponse('This mobile number is already added', 'DUPLICATE', 422);
            }
        }

        $mobile->update($validated);

        // If set as primary, update others
        if (isset($validated['is_primary']) && $validated['is_primary']) {
            $mobile->setAsPrimary();
        }

        return $this->successResponse($mobile, 'Mobile updated successfully');
    }

    /**
     * Delete a mobile number.
     * DELETE /api/v1/me/mobiles/{id}
     */
    public function destroy(Request $request, $id)
    {
        $mobile = $request->user()->mobiles()->find($id);

        if (! $mobile) {
            return $this->notFoundResponse('Mobile not found');
        }

        $wasPrimary = $mobile->is_primary;
        $mobile->delete();

        // If deleted mobile was primary, set another one as primary
        if ($wasPrimary) {
            $firstMobile = $request->user()->mobiles()->first();
            if ($firstMobile) {
                $firstMobile->update(['is_primary' => true]);
            }
        }

        return $this->successResponse([], 'Mobile deleted successfully');
    }

    /**
     * Set a mobile as primary.
     * POST /api/v1/me/mobiles/{id}/set-primary
     */
    public function setPrimary(Request $request, $id)
    {
        $mobile = $request->user()->mobiles()->find($id);

        if (! $mobile) {
            return $this->notFoundResponse('Mobile not found');
        }

        $mobile->setAsPrimary();

        return $this->successResponse($mobile, 'Mobile set as primary');
    }
}
