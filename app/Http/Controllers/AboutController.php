<?php

namespace App\Http\Controllers;

use App\Models\AboutSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AboutController extends Controller
{
    /**
     * Get about page settings (Public endpoint).
     */
    public function index()
    {
        $about = AboutSetting::getInstance();

        return response()->json([
            'success' => true,
            'data' => [
                'about' => $about,
            ],
        ]);
    }

    /**
     * Update about page settings (Admin only).
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hero_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:1024',
            'title_ar' => 'nullable|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description1_ar' => 'nullable|string',
            'description1_en' => 'nullable|string',
            'description2_ar' => 'nullable|string',
            'description2_en' => 'nullable|string',
            'mission_title_ar' => 'nullable|string|max:255',
            'mission_title_en' => 'nullable|string|max:255',
            'mission_description_ar' => 'nullable|string',
            'mission_description_en' => 'nullable|string',
            'values_title_ar' => 'nullable|string|max:255',
            'values_title_en' => 'nullable|string|max:255',
            'values_description_ar' => 'nullable|string',
            'values_description_en' => 'nullable|string',
            'vision_title_ar' => 'nullable|string|max:255',
            'vision_title_en' => 'nullable|string|max:255',
            'vision_description_ar' => 'nullable|string',
            'vision_description_en' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $about = AboutSetting::getInstance();
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
            'description1_ar', 'description1_en',
            'description2_ar', 'description2_en',
            'mission_title_ar', 'mission_title_en',
            'mission_description_ar', 'mission_description_en',
            'values_title_ar', 'values_title_en',
            'values_description_ar', 'values_description_en',
            'vision_title_ar', 'vision_title_en',
            'vision_description_ar', 'vision_description_en',
        ];

        foreach ($textFields as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        $about->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'About settings updated successfully',
            'data' => [
                'about' => $about->fresh(),
            ],
        ]);
    }

    /**
     * Remove the hero image (Admin only).
     */
    public function removeImage()
    {
        $about = AboutSetting::getInstance();
        $about->update(['hero_image' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Hero image removed successfully',
            'data' => [
                'about' => $about->fresh(),
            ],
        ]);
    }
}
