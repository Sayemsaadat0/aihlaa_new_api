<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DiscountController extends Controller
{
    /**
     * Get all discounts (Public API)
     */
    public function index()
    {
        try {
            $discounts = Discount::all();
            
            return $this->successResponse([
                'discounts' => $discounts,
                'total' => $discounts->count(),
            ], 'Discounts retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve discounts: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get a specific discount by ID (Public API)
     */
    public function show($id)
    {
        try {
            $discount = Discount::find($id);
            
            if (!$discount) {
                return $this->notFoundResponse('Discount');
            }
            
            return $this->successResponse([
                'discount' => $discount,
            ], 'Discount retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve discount: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a new discount (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'status' => 'required|in:published,unpublished',
                'code' => 'required|string|max:255|unique:discounts,code',
                'discount_price' => 'required|numeric|min:0|max:99999999.99',
            ]);

            $discount = Discount::create([
                'status' => $request->status,
                'code' => $request->code,
                'discount_price' => $request->discount_price,
            ]);

            return $this->successResponse([
                'discount' => $discount,
            ], 'Discount created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create discount: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update a discount by ID (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $discount = Discount::find($id);
            
            if (!$discount) {
                return $this->notFoundResponse('Discount');
            }

            $request->validate([
                'status' => 'sometimes|in:published,unpublished',
                'code' => 'sometimes|string|max:255|unique:discounts,code,' . $id,
                'discount_price' => 'sometimes|numeric|min:0|max:99999999.99',
            ]);

            $updateData = [];
            
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
            }
            
            if ($request->has('code')) {
                $updateData['code'] = $request->code;
            }
            
            if ($request->has('discount_price')) {
                $updateData['discount_price'] = $request->discount_price;
            }

            if (empty($updateData)) {
                return $this->errorResponse(
                    'No fields provided for update',
                    400
                );
            }

            $discount->update($updateData);

            return $this->successResponse([
                'discount' => $discount->fresh(),
            ], 'Discount updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update discount: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a discount by ID (Admin only)
     */
    public function destroy($id)
    {
        try {
            $discount = Discount::find($id);
            
            if (!$discount) {
                return $this->notFoundResponse('Discount');
            }

            $discount->delete();

            return $this->successResponse(null, 'Discount deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete discount: ' . $e->getMessage(),
                500
            );
        }
    }
}
