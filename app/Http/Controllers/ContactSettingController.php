<?php

namespace App\Http\Controllers;

use App\Models\ContactSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactSettingController extends Controller
{
    /**
     * Get contact page settings (public)
     */
    public function index(): JsonResponse
    {
        $settings = ContactSetting::getInstance();

        return response()->json([
            'success' => true,
            'data' => $settings,
        ]);
    }

    /**
     * Update contact page settings (admin only)
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title_ar' => 'sometimes|string|max:255',
            'title_en' => 'sometimes|string|max:255',
            'description1_ar' => 'sometimes|nullable|string',
            'description1_en' => 'sometimes|nullable|string',
            'description2_ar' => 'sometimes|nullable|string',
            'description2_en' => 'sometimes|nullable|string',
        ]);

        $settings = ContactSetting::getInstance();
        $settings->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Contact page settings updated successfully',
            'data' => $settings->fresh(),
        ]);
    }
}
