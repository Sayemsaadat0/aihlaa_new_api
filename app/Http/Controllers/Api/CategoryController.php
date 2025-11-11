<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /**
     * Get all categories (Public API)
     */
    public function index()
    {
        try {
            $categories = Category::all();
            
            return $this->successResponse([
                'categories' => $categories,
                'total' => $categories->count(),
            ], 'Categories retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve categories: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get a specific category by ID (Public API)
     */
    public function show($id)
    {
        try {
            $category = Category::find($id);
            
            if (!$category) {
                return $this->notFoundResponse('Category');
            }
            
            return $this->successResponse([
                'category' => $category,
            ], 'Category retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve category: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a new category (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'status' => 'required|in:published,unpublished',
            ]);

            $category = Category::create([
                'name' => $request->name,
                'status' => $request->status,
            ]);

            return $this->successResponse([
                'category' => $category,
            ], 'Category created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create category: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update a category by ID (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $category = Category::find($id);
            
            if (!$category) {
                return $this->notFoundResponse('Category');
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'status' => 'sometimes|in:published,unpublished',
            ]);

            $updateData = [];
            
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }

            if (empty($updateData)) {
                return $this->errorResponse(
                    'No fields provided for update',
                    400
                );
            }

            $category->update($updateData);

            return $this->successResponse([
                'category' => $category->fresh(),
            ], 'Category updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update category: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a category by ID (Admin only)
     */
    public function destroy($id)
    {
        try {
            $category = Category::find($id);
            
            if (!$category) {
                return $this->notFoundResponse('Category');
            }

            $category->delete();

            return $this->successResponse(null, 'Category deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete category: ' . $e->getMessage(),
                500
            );
        }
    }
}
