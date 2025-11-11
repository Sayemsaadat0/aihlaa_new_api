<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CityController extends Controller
{
    /**
     * Get all cities (Public API)
     */
    public function index()
    {
        try {
            $cities = City::all();

            return $this->successResponse([
                'cities' => $cities,
                'total' => $cities->count(),
            ], 'Cities retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve cities: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get a specific city by ID (Public API)
     */
    public function show($id)
    {
        try {
            $city = City::find($id);

            if (!$city) {
                return $this->notFoundResponse('City');
            }

            return $this->successResponse([
                'city' => $city,
            ], 'City retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve city: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create a new city (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:cities,name',
                'status' => 'required|in:published,unpublished',
            ]);

            $city = City::create([
                'name' => $request->name,
                'status' => $request->status,
            ]);

            return $this->successResponse([
                'city' => $city,
            ], 'City created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create city: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Update a city by ID (Admin only)
     */
    public function update(Request $request, $id)
    {
        try {
            $city = City::find($id);

            if (!$city) {
                return $this->notFoundResponse('City');
            }

            $request->validate([
                'name' => 'sometimes|string|max:255|unique:cities,name,' . $id,
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

            $city->update($updateData);

            return $this->successResponse([
                'city' => $city->fresh(),
            ], 'City updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update city: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete a city by ID (Admin only)
     */
    public function destroy($id)
    {
        try {
            $city = City::find($id);

            if (!$city) {
                return $this->notFoundResponse('City');
            }

            $city->delete();

            return $this->successResponse(null, 'City deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete city: ' . $e->getMessage(),
                500
            );
        }
    }
}
