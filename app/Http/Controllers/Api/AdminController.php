<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    /**
     * Get all users (Admin only)
     */
    public function index()
    {
        try {
            $users = User::all();
            
            return $this->successResponse([
                'users' => $users,
                'total' => $users->count(),
            ], 'Users retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve users: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get a specific user by ID (Admin only)
     */
    public function show($id)
    {
        try {
            $user = User::find($id);
            
            if (!$user) {
                return $this->notFoundResponse('User');
            }
            
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
     * Create a new user (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'sometimes|in:user,admin',
            ]);

            // Check if user already exists
            $existingUser = User::where('email', $request->email)->first();
            
            if ($existingUser) {
                return $this->errorResponse(
                    'A user with this email address already exists.',
                    409
                );
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? User::ROLE_USER,
            ]);

            return $this->successResponse([
                'user' => $user,
            ], 'User created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create user: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update a user by ID (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);
            
            if (!$user) {
                return $this->notFoundResponse('User');
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'password' => 'sometimes|string|min:8|confirmed',
                'role' => 'sometimes|in:user,admin',
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
            
            if ($request->has('role')) {
                $updateData['role'] = $request->role;
            }

            if (empty($updateData)) {
                return $this->errorResponse(
                    'No fields provided for update',
                    400
                );
            }

            $user->update($updateData);

            return $this->successResponse([
                'user' => $user->fresh(),
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
     * Delete a user by ID (Admin only)
     */
    public function destroy($id)
    {
        try {
            $user = User::find($id);
            
            if (!$user) {
                return $this->notFoundResponse('User');
            }

            // Prevent admin from deleting themselves
            if ($user->id === auth()->id()) {
                return $this->forbiddenResponse('You cannot delete your own account');
            }

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

    /**
     * Get admin dashboard stats (Admin only)
     */
    public function dashboard()
    {
        try {
            $totalUsers = User::count();
            $totalAdmins = User::where('role', User::ROLE_ADMIN)->count();
            $totalRegularUsers = User::where('role', User::ROLE_USER)->count();
            
            return $this->successResponse([
                'stats' => [
                    'total_users' => $totalUsers,
                    'total_admins' => $totalAdmins,
                    'total_regular_users' => $totalRegularUsers,
                ],
            ], 'Dashboard stats retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve dashboard stats: ' . $e->getMessage(),
                500
            );
        }
    }
}
