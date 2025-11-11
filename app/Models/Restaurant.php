<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'privacy_policy',
        'terms',
        'refund_process',
        'license',
        'isShopOpen',
        'shop_name',
        'shop_address',
        'shop_details',
        'shop_phone',
        'tax',
        'delivery_charge',
        'shop_logo',
    ];

    protected function casts(): array
    {
        return [
            'isShopOpen' => 'boolean',
            'tax' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
        ];
    }
}
