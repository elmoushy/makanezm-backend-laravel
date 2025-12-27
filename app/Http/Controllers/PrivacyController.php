<?php

namespace App\Http\Controllers;

use App\Models\PrivacySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PrivacyController extends Controller
{
    /**
     * Get privacy page settings (Public endpoint).
     */
    public function index()
    {
        $privacy = PrivacySetting::getInstance();

        return response()->json([
            'success' => true,
            'data' => [
                'privacy' => $privacy,
            ],
        ]);
    }

    /**
     * Update privacy page settings (Admin only).
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'intro_ar' => 'nullable|array',
            'intro_ar.*' => 'string',
            'intro_en' => 'nullable|array',
            'intro_en.*' => 'string',
            'terms_title_ar' => 'nullable|string|max:255',
            'terms_title_en' => 'nullable|string|max:255',
            'terms_content_ar' => 'nullable|string',
            'terms_content_en' => 'nullable|string',
            'privacy_title_ar' => 'nullable|string|max:255',
            'privacy_title_en' => 'nullable|string|max:255',
            'privacy_content_ar' => 'nullable|string',
            'privacy_content_en' => 'nullable|string',
            'operation_title_ar' => 'nullable|string|max:255',
            'operation_title_en' => 'nullable|string|max:255',
            'operation_content_ar' => 'nullable|string',
            'operation_content_en' => 'nullable|string',
            'copyright_title_ar' => 'nullable|string|max:255',
            'copyright_title_en' => 'nullable|string|max:255',
            'copyright_content_ar' => 'nullable|string',
            'copyright_content_en' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $privacy = PrivacySetting::getInstance();
        $updateData = [];

        // Handle image upload
        if ($request->hasFile('hero_image')) {
            $imageData = file_get_contents($request->file('hero_image')->getRealPath());
            $mimeType = $request->file('hero_image')->getMimeType();
            $updateData['hero_image'] = 'data:'.$mimeType.';base64,'.base64_encode($imageData);
        }

        // Update text fields
        $textFields = [
            'title_ar', 'title_en',
            'terms_title_ar', 'terms_title_en',
            'terms_content_ar', 'terms_content_en',
            'privacy_title_ar', 'privacy_title_en',
            'privacy_content_ar', 'privacy_content_en',
            'operation_title_ar', 'operation_title_en',
            'operation_content_ar', 'operation_content_en',
            'copyright_title_ar', 'copyright_title_en',
            'copyright_content_ar', 'copyright_content_en',
        ];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        // Handle intro arrays
        if ($request->has('intro_ar')) {
            $updateData['intro_ar'] = $request->input('intro_ar');
        }
        if ($request->has('intro_en')) {
            $updateData['intro_en'] = $request->input('intro_en');
        }

        $privacy->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Privacy settings updated successfully',
            'data' => [
                'privacy' => $privacy->fresh(),
            ],
        ]);
    }

    /**
     * Remove the hero image (Admin only).
     */
    public function removeImage()
    {
        $privacy = PrivacySetting::getInstance();
        $privacy->update(['hero_image' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Hero image removed successfully',
            'data' => [
                'privacy' => $privacy->fresh(),
            ],
        ]);
    }
}
