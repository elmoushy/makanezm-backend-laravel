<?php

namespace App\Http\Controllers;

use App\Http\Traits\ApiResponseTrait;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Register a new user.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
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
            'role' => 'USER',
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
        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->createdResponse([
            'user' => [
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
            ],
            'token' => $token,
        ], 'User registered successfully');
    }

    /**
     * Login user and create token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'token' => $token,
        ], 'Login successful');
    }

    /**
     * Logout user (revoke token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse([], 'Logged out successfully');
    }

    /**
     * Get authenticated user.
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'created_at' => $user->created_at,
        ], 'User data retrieved successfully');
    }
}
