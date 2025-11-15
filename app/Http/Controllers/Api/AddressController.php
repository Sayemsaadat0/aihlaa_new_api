<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AddressController extends Controller
{
    /**
     * Store a newly created address (Public API)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id',
                'city_id' => 'required|integer|exists:cities,id',
                'state' => 'required|string|max:255',
                'zip_code' => 'required|string|max:50',
                'street_address' => 'required|string',
            ]);

            $address = Address::create([
                'user_id' => $request->user_id,
                'city_id' => $request->city_id,
                'state' => $request->state,
                'zip_code' => $request->zip_code,
                'street_address' => $request->street_address,
            ]);

            return $this->successResponse([
                'address' => $address->load(['user', 'city']),
            ], 'Address created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create address: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display a listing of addresses (Admin only)
     */
    public function index()
    {
        try {
            $addresses = Address::with(['user', 'city'])
                ->orderByDesc('created_at')
                ->get();

            return $this->successResponse([
                'addresses' => $addresses,
                'total' => $addresses->count(),
            ], 'Addresses retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve addresses: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Display the specified address (Admin only)
     */
    public function show($id)
    {
        try {
            $address = Address::with(['user', 'city'])->find($id);

            if (!$address) {
                return $this->notFoundResponse('Address');
            }

            return $this->successResponse([
                'address' => $address,
            ], 'Address retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve address: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update the specified address (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $address = Address::find($id);

            if (!$address) {
                return $this->notFoundResponse('Address');
            }

            $request->validate([
                'user_id' => 'sometimes|integer|exists:users,id',
                'city_id' => 'sometimes|integer|exists:cities,id',
                'state' => 'sometimes|string|max:255',
                'zip_code' => 'sometimes|string|max:50',
                'street_address' => 'sometimes|string',
            ]);

            $updateData = [];

            if ($request->has('user_id')) {
                $updateData['user_id'] = $request->user_id;
            }

            if ($request->has('city_id')) {
                $updateData['city_id'] = $request->city_id;
            }

            if ($request->has('state')) {
                $updateData['state'] = $request->state;
            }

            if ($request->has('zip_code')) {
                $updateData['zip_code'] = $request->zip_code;
            }

            if ($request->has('street_address')) {
                $updateData['street_address'] = $request->street_address;
            }

            if (empty($updateData)) {
                return $this->errorResponse('No fields provided for update', 400);
            }

            $address->update($updateData);

            return $this->successResponse([
                'address' => $address->load(['user', 'city']),
            ], 'Address updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update address: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified address (Admin only)
     */
    public function destroy($id)
    {
        try {
            $address = Address::find($id);

            if (!$address) {
                return $this->notFoundResponse('Address');
            }

            $address->delete();

            return $this->successResponse(null, 'Address deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete address: ' . $e->getMessage(),
                500
            );
        }
    }
}


