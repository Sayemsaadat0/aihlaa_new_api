<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RestaurantController extends Controller
{
    /**
     * Get MyRestaurant (public)
     */
    public function show()
    {
        try {
            $restaurant = Restaurant::first();
            
            if (!$restaurant) {
                return $this->notFoundResponse('MyRestaurant');
            }

            return $this->successResponse([
                'restaurant' => $this->serialize($restaurant),
            ], 'MyRestaurant retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to retrieve MyRestaurant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create MyRestaurant (singleton)
     */
    public function store(Request $request)
    {
        try {
            // Enforce singleton
            if (Restaurant::query()->exists()) {
                return $this->errorResponse('MyRestaurant already exists', 409);
            }

            // Coerce boolean values before validation
            $input = $request->all();
            if (isset($input['isShopOpen'])) {
                $input['isShopOpen'] = $this->coerceBoolean($input['isShopOpen']);
            }
            $request->merge($input);

            $request->validate([
                'privacy_policy' => 'nullable|string',
                'terms' => 'nullable|string',
                'refund_process' => 'nullable|string',
                'license' => 'nullable|string',
                'isShopOpen' => 'nullable|boolean',
                'shop_name' => 'nullable|string|max:255',
                'shop_address' => 'nullable|string',
                'shop_details' => 'nullable|string',
                'shop_phone' => ['nullable','string','max:25','regex:/^[+\d\s\-()]+$/'],
                'tax' => 'nullable|numeric|min:0|max:100',
                'delivery_charge' => 'nullable|numeric|min:0',
                'shop_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            ]);

            $data = $this->coerceAndTrim($request);
            
            // Set default value for isShopOpen if not provided
            if (!isset($data['isShopOpen'])) {
                $data['isShopOpen'] = false;
            }

            if ($request->hasFile('shop_logo')) {
                $data['shop_logo'] = FileUploadService::uploadToPublicUploads($request->file('shop_logo'));
            }

            $restaurant = Restaurant::create($data);

            return $this->successResponse([
                'restaurant' => $this->serialize($restaurant),
            ], 'MyRestaurant created successfully', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create MyRestaurant: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update MyRestaurant by id
     */
    public function update(Request $request, $id)
    {
        try {
            $restaurant = Restaurant::find($id);
            if (!$restaurant) {
                return $this->notFoundResponse('MyRestaurant');
            }

            // Coerce boolean values before validation
            $input = $request->all();
            if (isset($input['isShopOpen'])) {
                $input['isShopOpen'] = $this->coerceBoolean($input['isShopOpen']);
            }
            $request->merge($input);

            $request->validate([
                'privacy_policy' => 'nullable|string',
                'terms' => 'nullable|string',
                'refund_process' => 'nullable|string',
                'license' => 'nullable|string',
                'isShopOpen' => 'nullable|boolean',
                'shop_name' => 'nullable|string|max:255',
                'shop_address' => 'nullable|string',
                'shop_details' => 'nullable|string',
                'shop_phone' => ['nullable','string','max:25','regex:/^[+\d\s\-()]+$/'],
                'tax' => 'nullable|numeric|min:0|max:100',
                'delivery_charge' => 'nullable|numeric|min:0',
                'shop_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            ]);

            $data = $this->coerceAndTrim($request, partial: true);

            if ($request->hasFile('shop_logo')) {
                // Delete old file
                FileUploadService::deletePublicFileByAbsoluteUrl($restaurant->shop_logo);
                $data['shop_logo'] = FileUploadService::uploadToPublicUploads($request->file('shop_logo'));
            }

            if (!empty($data)) {
                $restaurant->update($data);
            }

            return $this->successResponse([
                'restaurant' => $this->serialize($restaurant->fresh()),
            ], 'MyRestaurant updated successfully');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update MyRestaurant: ' . $e->getMessage(), 500);
        }
    }

    private function coerceAndTrim(Request $request, bool $partial = false): array
    {
        $fields = [
            'privacy_policy','terms','refund_process','license','shop_name','shop_address','shop_details','shop_phone'
        ];
        $data = [];
        foreach ($fields as $f) {
            if ($partial && !$request->has($f)) continue;
            $val = $request->input($f);
            if ($val !== null) {
                $data[$f] = is_string($val) ? trim($val) : $val;
            }
        }
        // Booleans
        if (!$partial || $request->has('isShopOpen')) {
            $v = $request->input('isShopOpen');
            if ($v !== null) {
                $data['isShopOpen'] = $this->coerceBoolean($v);
            }
        }
        // Numerics
        foreach (['tax','delivery_charge'] as $n) {
            if ($partial && !$request->has($n)) continue;
            $v = $request->input($n);
            if ($v !== null && $v !== '') $data[$n] = round((float)$v, 2);
        }
        return $data;
    }

    /**
     * Coerce various boolean representations to actual boolean
     */
    private function coerceBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_string($value)) {
            $value = strtolower(trim($value));
            return in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        
        if (is_numeric($value)) {
            return (bool) $value;
        }
        
        return (bool) $value;
    }

    private function serialize(Restaurant $r): array
    {
        return [
            'id' => $r->id,
            'privacy_policy' => $r->privacy_policy,
            'terms' => $r->terms,
            'refund_process' => $r->refund_process,
            'license' => $r->license,
            'isShopOpen' => (bool) $r->isShopOpen,
            'shop_name' => $r->shop_name,
            'shop_address' => $r->shop_address,
            'shop_details' => $r->shop_details,
            'shop_phone' => $r->shop_phone,
            'tax' => $r->tax !== null ? (float) $r->tax : null,
            'delivery_charge' => $r->delivery_charge !== null ? (float) $r->delivery_charge : null,
            'shop_logo' => $r->shop_logo,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ];
    }
}
