<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\FooterLink;
use Illuminate\Http\Request;

class FooterLinkController extends Controller
{
    use ApiResponseTrait;

    /**
     * Get all footer links (Public).
     * GET /api/v1/footer-links
     */
    public function index()
    {
        $links = FooterLink::getLinksMap();

        return $this->successResponse([
            'links' => $links,
        ]);
    }

    /**
     * Update footer links (Admin only).
     * POST /api/v1/admin/footer-links
     *
     * Expects: { "whatsapp": "https://...", "facebook": "https://...", ... }
     */
    public function update(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update footer links.');
        }

        $platforms = ['whatsapp', 'facebook', 'youtube', 'linkedin', 'twitter'];

        $validated = $request->validate([
            'whatsapp' => 'nullable|string|max:500',
            'facebook' => 'nullable|string|max:500',
            'youtube' => 'nullable|string|max:500',
            'linkedin' => 'nullable|string|max:500',
            'twitter' => 'nullable|string|max:500',
        ]);

        foreach ($platforms as $platform) {
            if (array_key_exists($platform, $validated)) {
                FooterLink::updateOrCreate(
                    ['platform' => $platform],
                    ['url' => $validated[$platform]]
                );
            }
        }

        return $this->successResponse([
            'links' => FooterLink::getLinksMap(),
        ], 'Footer links updated successfully');
    }
}
