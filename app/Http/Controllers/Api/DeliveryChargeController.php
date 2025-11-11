<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryCharge;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeliveryChargeController extends Controller
{
    /**
     * Get delivery charges (Public API)
     */
    public function index(Request $request)
    {
        try {
            $request->validate([
                'city_id' => 'nullable|exists:cities,id',
            ]);

            $query = DeliveryCharge::with('city');

            if ($request->filled('city_id')) {
                $query->where('city_id', $request->city_id);
            }

            $charges = $query->get();

            $data = $charges->map(function (DeliveryCharge $charge) {
                return [
                    'id' => $charge->id,
                    'city' => $charge->city ? [
                        'id' => $charge->city->id,
                        'name' => $charge->city->name,
                    ] : null,
                    'charge' => (float) $charge->charge,
                    'status' => $charge->status,
                    'created_at' => $charge->created_at,
                    'updated_at' => $charge->updated_at,
                ];
            });

            return $this->successResponse([
                'delivery_charges' => $data,
                'total' => $data->count(),
            ], 'Delivery charges retrieved successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve delivery charges: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get a delivery charge by ID (Public API)
     */
    public function show($id)
    {
        try {
            $charge = DeliveryCharge::with('city')->find($id);

            if (!$charge) {
                return $this->notFoundResponse('Delivery charge');
            }

            return $this->successResponse([
                'delivery_charge' => [
                    'id' => $charge->id,
                    'city' => $charge->city ? [
                        'id' => $charge->city->id,
                        'name' => $charge->city->name,
                    ] : null,
                    'charge' => (float) $charge->charge,
                    'status' => $charge->status,
                    'created_at' => $charge->created_at,
                    'updated_at' => $charge->updated_at,
                ],
            ], 'Delivery charge retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve delivery charge: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a delivery charge (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'city_id' => 'required|exists:cities,id',
                'charge' => 'required|numeric|min:0|max:99999999.99',
                'status' => 'required|in:published,unpublished',
            ]);

            $charge = DeliveryCharge::create([
                'city_id' => $request->city_id,
                'charge' => $request->charge,
                'status' => $request->status,
            ]);

            $charge->load('city');

            return $this->successResponse([
                'delivery_charge' => [
                    'id' => $charge->id,
                    'city' => $charge->city ? [
                        'id' => $charge->city->id,
                        'name' => $charge->city->name,
                    ] : null,
                    'charge' => (float) $charge->charge,
                    'status' => $charge->status,
                    'created_at' => $charge->created_at,
                    'updated_at' => $charge->updated_at,
                ],
            ], 'Delivery charge created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create delivery charge: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update a delivery charge (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $charge = DeliveryCharge::find($id);

            if (!$charge) {
                return $this->notFoundResponse('Delivery charge');
            }

            $request->validate([
                'city_id' => 'sometimes|exists:cities,id',
                'charge' => 'sometimes|numeric|min:0|max:99999999.99',
                'status' => 'sometimes|in:published,unpublished',
            ]);

            $updateData = [];

            if ($request->has('city_id')) {
                $updateData['city_id'] = $request->city_id;
            }

            if ($request->has('charge')) {
                $updateData['charge'] = $request->charge;
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

            $charge->update($updateData);
            $charge->load('city');

            return $this->successResponse([
                'delivery_charge' => [
                    'id' => $charge->id,
                    'city' => $charge->city ? [
                        'id' => $charge->city->id,
                        'name' => $charge->city->name,
                    ] : null,
                    'charge' => (float) $charge->charge,
                    'status' => $charge->status,
                    'created_at' => $charge->created_at,
                    'updated_at' => $charge->updated_at,
                ],
            ], 'Delivery charge updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update delivery charge: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a delivery charge (Admin only)
     */
    public function destroy($id)
    {
        try {
            $charge = DeliveryCharge::find($id);

            if (!$charge) {
                return $this->notFoundResponse('Delivery charge');
            }

            $charge->delete();

            return $this->successResponse(null, 'Delivery charge deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete delivery charge: ' . $e->getMessage(),
                500
            );
        }
    }
}
