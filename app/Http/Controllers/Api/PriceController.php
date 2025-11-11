<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemPrice;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PriceController extends Controller
{
    /**
     * Create a new price for an item (Admin only)
     */
    public function store(Request $request, $itemId)
    {
        try {
            // Check if item exists
            $item = Item::find($itemId);
            
            if (!$item) {
                return $this->notFoundResponse('Item');
            }

            $request->validate([
                'price' => 'required|numeric|min:0|max:99999999.99',
            ]);

            $price = ItemPrice::create([
                'item_id' => $itemId,
                'price' => $request->price,
            ]);

            return $this->successResponse([
                'price' => [
                    'id' => $price->id,
                    'price' => (float) $price->price,
                ],
            ], 'Price created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create price: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update a price by ID (Admin only)
     */
    public function update(Request $request, $itemId, $priceId)
    {
        try {
            // Check if item exists
            $item = Item::find($itemId);
            
            if (!$item) {
                return $this->notFoundResponse('Item');
            }

            $price = ItemPrice::where('id', $priceId)
                ->where('item_id', $itemId)
                ->first();
            
            if (!$price) {
                return $this->notFoundResponse('Price');
            }

            $request->validate([
                'price' => 'required|numeric|min:0|max:99999999.99',
            ]);

            $price->update([
                'price' => $request->price,
            ]);

            return $this->successResponse([
                'price' => [
                    'id' => $price->id,
                    'price' => (float) $price->price,
                ],
            ], 'Price updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update price: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a price by ID (Admin only)
     */
    public function destroy($itemId, $priceId)
    {
        try {
            // Check if item exists
            $item = Item::find($itemId);
            
            if (!$item) {
                return $this->notFoundResponse('Item');
            }

            $price = ItemPrice::where('id', $priceId)
                ->where('item_id', $itemId)
                ->first();
            
            if (!$price) {
                return $this->notFoundResponse('Price');
            }

            $price->delete();

            return $this->successResponse(null, 'Price deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete price: ' . $e->getMessage(),
                500
            );
        }
    }
}
