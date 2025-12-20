<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\PartnershipRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PartnershipController extends Controller
{
    use ApiResponseTrait;

    /**
     * Submit a new partnership request.
     * POST /api/v1/partnerships
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'partnership_type' => 'required|in:distribution,reseller,collaboration,other',
            'message' => 'required|string|max:2000',
        ]);

        $partnership = PartnershipRequest::create([
            'user_id' => $request->user()?->id,
            'company_name' => $validated['company_name'],
            'contact_name' => $validated['contact_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'partnership_type' => $validated['partnership_type'],
            'message' => $validated['message'],
            'status' => 'pending',
        ]);

        return $this->createdResponse([
            'id' => $partnership->id,
            'company_name' => $partnership->company_name,
            'partnership_type' => $partnership->partnership_type,
            'status' => $partnership->status,
            'created_at' => $partnership->created_at->toISOString(),
        ], 'Partnership request submitted successfully');
    }

    /**
     * Get user's partnership requests.
     * GET /api/v1/partnerships
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PartnershipRequest::query();

        // Non-admins only see their own requests
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $partnerships = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        $formattedPartnerships = $partnerships->getCollection()->map(function ($p) {
            return [
                'id' => $p->id,
                'company_name' => $p->company_name,
                'contact_name' => $p->contact_name,
                'email' => $p->email,
                'phone' => $p->phone,
                'partnership_type' => $p->partnership_type,
                'message' => $p->message,
                'status' => $p->status,
                'admin_notes' => $p->admin_notes,
                'reviewed_at' => $p->reviewed_at?->toISOString(),
                'created_at' => $p->created_at->toISOString(),
            ];
        });

        return $this->successResponse([
            'partnerships' => $formattedPartnerships,
            'pagination' => [
                'current_page' => $partnerships->currentPage(),
                'last_page' => $partnerships->lastPage(),
                'per_page' => $partnerships->perPage(),
                'total' => $partnerships->total(),
            ],
        ], 'Partnership requests retrieved successfully');
    }

    /**
     * Get a specific partnership request.
     * GET /api/v1/partnerships/{id}
     */
    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $partnership = PartnershipRequest::find($id);

        if (! $partnership) {
            return $this->notFoundResponse('Partnership request not found');
        }

        // Check authorization
        if (! $user->isAdmin() && $partnership->user_id !== $user->id) {
            return $this->forbiddenResponse('You are not authorized to view this request');
        }

        return $this->successResponse([
            'id' => $partnership->id,
            'company_name' => $partnership->company_name,
            'contact_name' => $partnership->contact_name,
            'email' => $partnership->email,
            'phone' => $partnership->phone,
            'partnership_type' => $partnership->partnership_type,
            'message' => $partnership->message,
            'status' => $partnership->status,
            'admin_notes' => $partnership->admin_notes,
            'reviewed_at' => $partnership->reviewed_at?->toISOString(),
            'reviewed_by' => $partnership->reviewer?->name,
            'created_at' => $partnership->created_at->toISOString(),
        ], 'Partnership request retrieved successfully');
    }

    /**
     * Update partnership request status (Admin only).
     * PUT /api/v1/admin/partnerships/{id}
     */
    public function update(Request $request, int $id)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update partnership requests');
        }

        $partnership = PartnershipRequest::find($id);

        if (! $partnership) {
            return $this->notFoundResponse('Partnership request not found');
        }

        $validated = $request->validate([
            'status' => 'sometimes|required|in:pending,approved,rejected,under_review',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        if (isset($validated['status'])) {
            $partnership->status = $validated['status'];
            $partnership->reviewed_at = Carbon::now();
            $partnership->reviewed_by = $user->id;
        }

        if (isset($validated['admin_notes'])) {
            $partnership->admin_notes = $validated['admin_notes'];
        }

        $partnership->save();

        return $this->successResponse([
            'id' => $partnership->id,
            'status' => $partnership->status,
            'admin_notes' => $partnership->admin_notes,
            'reviewed_at' => $partnership->reviewed_at?->toISOString(),
            'reviewed_by' => $user->name,
        ], 'Partnership request updated successfully');
    }

    /**
     * Delete a partnership request (Admin only).
     * DELETE /api/v1/admin/partnerships/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        if (! $user->isAdmin()) {
            return $this->forbiddenResponse('Only admins can delete partnership requests');
        }

        $partnership = PartnershipRequest::find($id);

        if (! $partnership) {
            return $this->notFoundResponse('Partnership request not found');
        }

        $partnership->delete();

        return $this->successResponse([], 'Partnership request deleted successfully');
    }
}
