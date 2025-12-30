<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        try {
            // Check if user already exists
            $existingUser = User::where('email', $request->email)->first();
            
            if ($existingUser) {
                return $this->errorResponse(
                    'A user with this email address already exists.',
                    409
                );
            }

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => User::ROLE_USER,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            // Load delivery address relationship
            $user->load('delivery_address.city');

            return $this->successResponse([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 'User registered successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to register user: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            $user = User::where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return $this->errorResponse(
                    'The provided credentials are incorrect.',
                    401
                );
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            // Load delivery address relationship
            $user->load('delivery_address.city');

            return $this->successResponse([
                'user' => $user,
                'access_token' => $token,
                'token_type' => 'Bearer',
            ], 'Login successful');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to login: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Logout user (Revoke the token)
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return $this->successResponse(null, 'Logged out successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to logout: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request)
    {
        try {
            $user = $request->user();
            
            // Load delivery address relationship
            $user->load('delivery_address.city');

            return $this->successResponse([
                'user' => $user,
            ], 'User retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve user: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update authenticated user
     */
    public function update(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'password' => 'sometimes|string|min:8|confirmed',
            ]);

            $updateData = [];
            
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            
            if ($request->has('email')) {
                $updateData['email'] = $request->email;
            }
            
            if ($request->has('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            if (empty($updateData)) {
                return $this->errorResponse(
                    'No fields provided for update',
                    400
                );
            }

            $user->update($updateData);

            // Refresh user and load delivery address
            $user->refresh();
            $user->load('delivery_address.city');

            return $this->successResponse([
                'user' => $user,
            ], 'User updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update user: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'previous_password' => 'required|string',
                'new_password' => 'required|string|min:8',
                'confirm_password' => 'required|string|same:new_password',
            ]);

            // Verify previous password
            if (!Hash::check($request->previous_password, $user->password)) {
                return $this->errorResponse(
                    'The previous password is incorrect.',
                    422
                );
            }

            // Check if new password is different from previous password
            if (Hash::check($request->new_password, $user->password)) {
                return $this->errorResponse(
                    'The new password must be different from your current password.',
                    422
                );
            }

            // Update password
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);

            return $this->successResponse(null, 'Password changed successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to change password: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete authenticated user
     */
    public function delete(Request $request)
    {
        try {
            $user = $request->user();
            
            // Revoke all tokens
            $user->tokens()->delete();
            
            // Delete user
            $user->delete();

            return $this->successResponse(null, 'User deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete user: ' . $e->getMessage(),
                500
            );
        }
    }
}

