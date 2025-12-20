<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use ApiResponseTrait;

    /**
     * Create a new user with specified role (Admin only).
     * POST /api/v1/admin/users
     * Auth: Admin only
     *
     * Admin can create users with role USER or ADMIN by specifying role in request body.
     * Default role is USER if not specified.
     *
     * Note: This is different from /register which is for self-registration
     * and returns a token. This endpoint is for admins to create user accounts.
     */
    public function createUser(Request $request)
    {
        // Admin-only: only admins can create users via this endpoint
        // For self-registration, use /register endpoint instead
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can create users. Use /register for self-registration.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'nullable|in:USER,ADMIN', // Role is optional, defaults to USER
            'city' => 'required|string|max:255',
            'national_id' => 'required|string|max:50|unique:users,national_id',
            'national_id_type' => 'nullable|string|max:100',
            'bank_iban' => 'required|string|max:50',
            'bank_name' => 'required|string|max:255',
            // Mobile numbers
            'primary_mobile' => 'required|string|max:20',
            'secondary_mobiles' => 'nullable|array',
            'secondary_mobiles.*.mobile' => 'required|string|max:20',
            'secondary_mobiles.*.label' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'] ?? 'USER', // Default to USER if not specified
            'city' => $validated['city'],
            'national_id' => $validated['national_id'],
            'national_id_type' => $validated['national_id_type'] ?? 'Saudi Arabian',
            'bank_iban' => $validated['bank_iban'],
            'bank_name' => $validated['bank_name'],
        ]);

        // Add primary mobile (required)
        $user->mobiles()->create([
            'mobile' => $validated['primary_mobile'],
            'label' => 'primary',
            'is_primary' => true,
        ]);

        // Add secondary mobiles if provided
        if (! empty($validated['secondary_mobiles'])) {
            foreach ($validated['secondary_mobiles'] as $mobileData) {
                $user->mobiles()->create([
                    'mobile' => $mobileData['mobile'],
                    'label' => $mobileData['label'] ?? 'secondary',
                    'is_primary' => false,
                ]);
            }
        }

        $user->load('mobiles');
        $message = $user->role === 'ADMIN' ? 'Admin created successfully' : 'User created successfully';

        return $this->createdResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'city' => $user->city,
            'national_id' => $user->national_id,
            'national_id_type' => $user->national_id_type,
            'bank_iban' => $user->bank_iban,
            'bank_name' => $user->bank_name,
            'mobiles' => $user->mobiles,
            'created_at' => $user->created_at,
        ], $message);
    }

    /**
     * Get all users (Admin only).
     */
    public function index(Request $request)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view users list');
        }

        $query = User::with('mobiles')
            ->select('id', 'name', 'email', 'role', 'city', 'national_id', 'bank_iban', 'bank_name', 'created_at', 'updated_at');

        // Filter by role
        if ($request->has('role') && in_array($request->role, ['USER', 'ADMIN'])) {
            $query->where('role', $request->role);
        }

        // Search by name or email
        if ($request->has('search') && ! empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 20);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->successResponse([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ], 'Users retrieved successfully');
    }

    /**
     * Get specific user by ID (Admin only).
     */
    public function show(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can view user details');
        }

        $user = User::with(['mobiles'])->find($id);

        if (! $user) {
            return $this->notFoundResponse('User not found');
        }

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobiles' => $user->mobiles,
            'city' => $user->city,
            'national_id' => $user->national_id,
            'national_id_type' => $user->national_id_type,
            'bank_iban' => $user->bank_iban,
            'bank_name' => $user->bank_name,
            'created_at' => $user->created_at,
        ], 'User retrieved successfully');
    }

    /**
     * Get current user's profile.
     * GET /api/v1/me/profile
     * Auth: User required
     */
    public function getMyProfile(Request $request)
    {
        $user = $request->user()->load(['mobiles']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobiles' => $user->mobiles,
            'city' => $user->city,
            'national_id' => $user->national_id,
            'national_id_type' => $user->national_id_type,
            'bank_iban' => $user->bank_iban,
            'bank_name' => $user->bank_name,
            'has_complete_profile' => $user->hasCompleteProfile(),
            'has_bank_info' => $user->hasBankInfo(),
            'created_at' => $user->created_at,
        ], 'Profile retrieved successfully');
    }

    /**
     * Update current user's profile.
     * PUT/PATCH /api/v1/me/profile
     * Auth: User required
     */
    public function updateMyProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$user->id,
            'city' => 'sometimes|required|string|max:255',
            'national_id' => 'sometimes|required|string|max:50|unique:users,national_id,'.$user->id,
            'national_id_type' => 'sometimes|string|max:100',
            'bank_iban' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'mobile' => 'sometimes|required|string|max:20',
        ]);

        // Remove mobile from validated array as it's not in users table
        $userData = collect($validated)->except(['mobile'])->toArray();

        $user->update($userData);

        // Update mobile if provided
        if ($request->has('mobile')) {
            $primaryMobile = $user->primaryMobile;
            if ($primaryMobile) {
                $primaryMobile->update(['mobile' => $request->mobile]);
            } else {
                $user->mobiles()->create([
                    'mobile' => $request->mobile,
                    'is_primary' => true,
                    'label' => 'primary',
                ]);
            }
        }

        $user->load(['mobiles']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobiles' => $user->mobiles,
            'city' => $user->city,
            'national_id' => $user->national_id,
            'national_id_type' => $user->national_id_type,
            'bank_iban' => $user->bank_iban,
            'bank_name' => $user->bank_name,
            'has_complete_profile' => $user->hasCompleteProfile(),
            'has_bank_info' => $user->hasBankInfo(),
            'updated_at' => $user->updated_at,
        ], 'Profile updated successfully');
    }

    /**
     * Update user by ID (Admin only).
     * PUT/PATCH /api/v1/admin/users/{id}
     * Auth: Admin required
     */
    public function updateUser(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can update users');
        }

        $user = User::find($id);

        if (! $user) {
            return $this->notFoundResponse('User not found');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,'.$user->id,
            'city' => 'sometimes|string|max:255',
            'national_id' => 'sometimes|string|max:50|unique:users,national_id,'.$user->id,
            'national_id_type' => 'sometimes|string|max:100',
            'bank_iban' => 'nullable|string|max:50',
            'bank_name' => 'nullable|string|max:255',
            'role' => 'sometimes|in:ADMIN,USER',
        ]);

        $user->update($validated);
        $user->load(['mobiles']);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'mobiles' => $user->mobiles,
            'city' => $user->city,
            'national_id' => $user->national_id,
            'national_id_type' => $user->national_id_type,
            'bank_iban' => $user->bank_iban,
            'bank_name' => $user->bank_name,
            'updated_at' => $user->updated_at,
        ], 'User updated successfully');
    }

    /**
     * Delete user by ID (Admin only).
     * DELETE /api/v1/admin/users/{id}
     * Auth: Admin required
     */
    public function deleteUser(Request $request, $id)
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            return $this->forbiddenResponse('Only admins can delete users');
        }

        $user = User::find($id);

        if (! $user) {
            return $this->notFoundResponse('User not found');
        }

        // Prevent deleting yourself
        if ($user->id === $request->user()->id) {
            return $this->errorResponse('Cannot delete your own account', 'FORBIDDEN', 403);
        }

        $user->delete();

        return $this->successResponse([], 'User deleted successfully');
    }

    /**
     * Change current user's password.
     * POST /api/v1/me/change-password
     * Auth: User required
     */
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            return $this->errorResponse('Current password is incorrect', 'INVALID_PASSWORD', 400);
        }

        // Check if new password is same as current
        if (Hash::check($validated['new_password'], $user->password)) {
            return $this->errorResponse('New password must be different from current password', 'SAME_PASSWORD', 400);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        return $this->successResponse([], 'Password changed successfully');
    }
}
