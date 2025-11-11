<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
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
     */
    public function index()
    {
        try {
            $items = Item::with(['category', 'prices'])->get();
            
            $items = $items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'details' => $item->details,
                    'thumbnail' => $this->getThumbnailUrl($item->thumbnail),
                    'status' => $item->status,
                    'category' => [
                        'id' => $item->category->id,
                        'name' => $item->category->name,
                        'slug' => Str::slug($item->category->name),
                    ],
                    'prices' => $item->prices->map(function ($price) {
                        return [
                            'id' => $price->id,
                            'price' => (float) $price->price,
                        ];
                    }),
                ];
            });
            
            return $this->successResponse([
                'items' => $items,
                'total' => $items->count(),
            ], 'Items retrieved successfully');
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
            ]);

            $item->load(['category', 'prices']);

            return $this->successResponse([
                'item' => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'details' => $item->details,
                    'thumbnail' => $this->getThumbnailUrl($item->thumbnail),
                    'status' => $item->status,
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
                    'category' => [
                        'id' => $item->category->id,
                        'name' => $item->category->name,
                        'slug' => Str::slug($item->category->name),
                    ],
                    'prices' => $item->prices->map(function ($price) {
                        return [
                            'id' => $price->id,
                            'price' => (float) $price->price,
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
}
