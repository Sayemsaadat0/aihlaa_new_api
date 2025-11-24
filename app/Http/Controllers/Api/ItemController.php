<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class ItemController extends Controller
{
    /**
     * Get full URL for thumbnail
     */
    private function getThumbnailUrl($thumbnailPath)
    {
        if (!$thumbnailPath) {
            return null;
        }
        
        $baseUrl = rtrim(config('app.asset_url'), '/');
        return $baseUrl . '/' . ltrim($thumbnailPath, '/');
    }

    /**
     * Get all items (Public API)
     * 
     * Query Parameters:
     * - category_id (optional): Filter items by category ID
     * - isSpecial (optional): Filter items by isSpecial status (true/false/1/0)
     */
    public function index(Request $request)
    {
        try {
            // Validate query parameters
            $request->validate([
                'category_id' => 'sometimes|integer',
                'isSpecial' => 'sometimes|in:true,false,1,0',
                'has_price' => 'sometimes|in:1,0',
            ]);

            $categoryId = $request->query('category_id');
            $isSpecial = $request->query('isSpecial');
            $hasPrice = $request->query('has_price');

            // If category_id is provided, check if it exists
            if ($categoryId) {
                $categoryExists = Category::where('id', $categoryId)->exists();
                
                if (!$categoryExists) {
                    return $this->notFoundResponse('Category');
                }
            }

            // Build items query
            $itemsQuery = Item::with(['category', 'prices']);

            // Filter by category_id if provided
            if ($categoryId) {
                $itemsQuery->where('category_id', $categoryId);
            }

            // Filter by isSpecial if provided
            if ($isSpecial !== null) {
                // Convert string boolean to actual boolean/integer for query
                $isSpecialValue = in_array($isSpecial, ['true', '1', 1], true);
                $itemsQuery->where('isSpecial', $isSpecialValue);
            }

            $items = $itemsQuery->get();
            
            // Filter by has_price if provided
            if ($hasPrice === '1') {
                $items = $items->filter(function ($item) {
                    return $item->prices->isNotEmpty();
                })->values(); // Reset array keys to ensure proper array structure
            }
            
            $items = $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'details' => $item->details,
                    'thumbnail' => $this->getThumbnailUrl($item->thumbnail),
                    'status' => $item->status,
                    'isSpecial' => (boolean) $item->isSpecial,
                    'category' => [
                        'id' => $item->category->id,
                        'name' => $item->category->name,
                        'slug' => Str::slug($item->category->name),
                    ],
                    'prices' => $item->prices->map(function ($price) {
                        return [
                            'id' => $price->id,
                            'price' => (float) $price->price,
                            'size' => $price->size,
                        ];
                    })->values(), // Reset array keys for prices
                ];
            })->values(); // Reset array keys to ensure proper array structure
            
            return $this->successResponse([
                'items' => $items,
                'total' => $items->count(),
            ], 'Items retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve items: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a new item (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'details' => 'required|string',
                'category_id' => 'required|exists:categories,id',
                'status' => 'required|in:published,unpublished',
                'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'isSpecial' => 'sometimes|in:true,false,1,0',
            ]);

            $thumbnailPath = null;
            
            if ($request->hasFile('thumbnail')) {
                try {
                    $file = $request->file('thumbnail');
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $uploadPath = public_path('thumbnails');
                    
                    // Create thumbnails directory if it doesn't exist
                    if (!File::exists($uploadPath)) {
                        File::makeDirectory($uploadPath, 0755, true);
                    }
                    
                    // Move file to public/thumbnails
                    $file->move($uploadPath, $filename);
                    $thumbnailPath = 'thumbnails/' . $filename;
                } catch (\Exception $e) {
                    return $this->errorResponse(
                        'Failed to upload thumbnail: ' . $e->getMessage(),
                        500
                    );
                }
            }

            $item = Item::create([
                'name' => $request->name,
                'details' => $request->details,
                'category_id' => $request->category_id,
                'status' => $request->status,
                'thumbnail' => $thumbnailPath,
                'isSpecial' => $request->boolean('isSpecial', false),
            ]);

            $item->load(['category', 'prices']);

            return $this->successResponse([
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'details' => $item->details,
                    'thumbnail' => $this->getThumbnailUrl($item->thumbnail),
                    'status' => $item->status,
                    'isSpecial' => (boolean) $item->isSpecial,
                    'category' => [
                        'id' => $item->category->id,
                        'name' => $item->category->name,
                        'slug' => Str::slug($item->category->name),
                    ],
                    'prices' => [],
                ],
            ], 'Item created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create item: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update an item by ID (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $item = Item::find($id);
            
            if (!$item) {
                return $this->notFoundResponse('Item');
            }

            $request->validate([
                'name' => 'sometimes|string|max:255',
                'details' => 'sometimes|string',
                'category_id' => 'sometimes|exists:categories,id',
                'status' => 'sometimes|in:published,unpublished',
                'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'isSpecial' => 'sometimes|in:true,false,1,0',
            ]);

            $updateData = [];
            
            // Check for text fields - properly detect form-data fields
            if ($request->has('name') && $request->input('name') !== null) {
                $updateData['name'] = $request->input('name');
            }
            
            if ($request->has('details') && $request->input('details') !== null) {
                $updateData['details'] = $request->input('details');
            }
            
            if ($request->has('category_id') && $request->input('category_id') !== null) {
                $updateData['category_id'] = $request->input('category_id');
            }
            
            if ($request->has('status') && $request->input('status') !== null) {
                $updateData['status'] = $request->input('status');
            }

            if ($request->has('isSpecial')) {
                $updateData['isSpecial'] = $request->boolean('isSpecial');
            }

            // Handle thumbnail update
            if ($request->hasFile('thumbnail')) {
                try {
                    // Delete old thumbnail if exists
                    if ($item->thumbnail) {
                        $oldThumbnailPath = public_path($item->thumbnail);
                        if (File::exists($oldThumbnailPath)) {
                            File::delete($oldThumbnailPath);
                        }
                    }
                    
                    $file = $request->file('thumbnail');
                    $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();
                    $uploadPath = public_path('thumbnails');
                    
                    // Create thumbnails directory if it doesn't exist
                    if (!File::exists($uploadPath)) {
                        File::makeDirectory($uploadPath, 0755, true);
                    }
                    
                    // Move file to public/thumbnails
                    $file->move($uploadPath, $filename);
                    $updateData['thumbnail'] = 'thumbnails/' . $filename;
                } catch (\Exception $e) {
                    return $this->errorResponse(
                        'Failed to upload thumbnail: ' . $e->getMessage(),
                        500
                    );
                }
            }

            if (empty($updateData)) {
                return $this->errorResponse(
                    'No fields provided for update',
                    400
                );
            }

            $item->update($updateData);
            $item->load(['category', 'prices']);

            return $this->successResponse([
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'details' => $item->details,
                    'thumbnail' => $this->getThumbnailUrl($item->thumbnail),
                    'status' => $item->status,
                    'isSpecial' => (boolean) $item->isSpecial,
                    'category' => [
                        'id' => $item->category->id,
                        'name' => $item->category->name,
                        'slug' => Str::slug($item->category->name),
                    ],
                    'prices' => $item->prices->map(function ($price) {
                        return [
                            'id' => $price->id,
                            'price' => (float) $price->price,
                            'size' => $price->size,
                        ];
                    }),
                ],
            ], 'Item updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update item: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete an item by ID (Admin only)
     */
    public function destroy($id)
    {
        try {
            $item = Item::find($id);
            
            if (!$item) {
                return $this->notFoundResponse('Item');
            }

            // Delete thumbnail if exists
            if ($item->thumbnail) {
                try {
                    $thumbnailPath = public_path($item->thumbnail);
                    if (File::exists($thumbnailPath)) {
                        File::delete($thumbnailPath);
                    }
                } catch (\Exception $e) {
                    // Log error but continue with deletion
                }
            }

            $item->delete();

            return $this->successResponse(null, 'Item deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete item: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get items grouped by category (Public API)
     * 
     * Query Parameters:
     * - category_id (optional): Filter by specific category ID
     * - status (optional): Filter categories by status (published/unpublished)
     * - item_status (optional): Filter items by status (published/unpublished)
     * - available_only (optional): If true, only return published items (default: false)
     * - has_price (optional): If 1, only return items that have prices
     * - search (optional): Search items by name/title
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getItemsByCategory(Request $request)
    {
        try {
            // Validate query parameters
            $validated = $request->validate([
                'category_id' => 'sometimes|integer|exists:categories,id',
                'status' => 'sometimes|in:published,unpublished',
                'item_status' => 'sometimes|in:published,unpublished',
                'available_only' => 'sometimes|string|in:true,false,1,0',
                'has_price' => 'sometimes|in:1,0',
                'search' => 'sometimes|string|max:255',
            ]);

            // Convert string boolean to actual boolean
            if (isset($validated['available_only'])) {
                $validated['available_only'] = in_array($validated['available_only'], ['true', '1', 1], true);
            }

            $hasPrice = $request->query('has_price');
            $search = $request->query('search');

            // Build category query
            $categoryQuery = Category::query();

            // Filter by category status if provided
            if (isset($validated['status'])) {
                $categoryQuery->where('status', $validated['status']);
            } else {
                // By default, only show published categories
                $categoryQuery->where('status', Category::STATUS_PUBLISHED);
            }

            // Filter by specific category ID if provided
            if (isset($validated['category_id'])) {
                $categoryQuery->where('id', $validated['category_id']);
            }

            // Get categories with their items
            $categories = $categoryQuery->with(['items' => function ($query) use ($validated, $search, $hasPrice) {
                // Eager load prices for items
                $query->with('prices');

                // Filter items by status if provided
                if (isset($validated['item_status'])) {
                    $query->where('status', $validated['item_status']);
                } elseif (isset($validated['available_only']) && $validated['available_only']) {
                    // If available_only is true, only show published items
                    $query->where('status', Item::STATUS_PUBLISHED);
                } else {
                    // By default, only show published items
                    $query->where('status', Item::STATUS_PUBLISHED);
                }

                // Filter by search term if provided
                if ($search) {
                    $query->where('name', 'like', '%' . $search . '%');
                }

                // Filter by has_price if provided
                if ($hasPrice === '1') {
                    $query->whereHas('prices');
                }

                // Order items by id (you can change this to any field)
                $query->orderBy('id', 'asc');
            }])->orderBy('id', 'asc')->get();

            // Transform data to match the requested structure
            $result = $categories->map(function ($category) use ($hasPrice) {
                // Filter items based on has_price if needed
                $items = $category->items;
                
                if ($hasPrice === '1') {
                    $items = $items->filter(function ($item) {
                        return $item->prices->isNotEmpty();
                    });
                }

                // Filter out categories with no items
                if ($items->isEmpty()) {
                    return null;
                }

                return [
                    'id' => $category->id,
                    'categoryName' => $category->name,
                    'description' => null, // Category description field doesn't exist in DB
                    'items' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'prices' => $item->prices->map(function ($price) {
                                return [
                                    'id' => $price->id,
                                    'price' => (float) $price->price,
                                    'size' => $price->size,
                                ];
                            }),
                            'thumbnail' => $this->getThumbnailUrl($item->thumbnail),
                            'description' => $item->details,
                            'isAvailable' => $item->status === Item::STATUS_PUBLISHED,
                            'isSpecial' => (boolean) $item->isSpecial,
                        ];
                    })->values(), // Reset array keys
                ];
            })->filter(function ($category) {
                // Remove null entries (categories with no items)
                return $category !== null;
            })->values(); // Reset array keys

            return $this->successResponse([
                'categories' => $result,
                'total' => $result->count(),
            ], 'Items retrieved successfully by category');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve items by category: ' . $e->getMessage(),
                500
            );
        }
    }
}
