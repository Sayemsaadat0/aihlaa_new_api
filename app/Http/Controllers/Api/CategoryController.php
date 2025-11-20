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
    public function index(Request $request)
    {
        try {
            // Validate query parameters
            $request->validate([
                'isSpecial' => 'sometimes|string',
                'has_price' => 'sometimes|in:1,0',
            ]);

            $isSpecial = $request->query('isSpecial');
            $hasPrice = $request->query('has_price');

            // Build query
            $categoriesQuery = Category::query();

            // Filter by isSpecial if provided
            if ($isSpecial !== null) {
                $categoriesQuery->where('isSpecial', $isSpecial);
            }

            // Filter by has_price if provided - only return categories that have items with prices
            if ($hasPrice === '1') {
                $categoriesQuery->whereHas('items', function ($query) {
                    $query->whereHas('prices');
                });
            }

            $categories = $categoriesQuery->get();

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
                'isSpecial' => 'sometimes|string',
            ]);

            $category = Category::create([
                'name' => $request->name,
                'status' => $request->status,
                'isSpecial' => $request->input('isSpecial', 'false'),
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
                'isSpecial' => 'sometimes|string',
            ]);

            $updateData = [];
            
            if ($request->filled('name')) {
                $updateData['name'] = $request->name;
            }
            
            if ($request->filled('status')) {
                $updateData['status'] = $request->status;
            }
            
            if (array_key_exists('isSpecial', $request->all())) {
                $updateData['isSpecial'] = $request->input('isSpecial');
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
