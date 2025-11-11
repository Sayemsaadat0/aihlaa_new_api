<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'guest_id',
        'user_id',
        'item_id',
        'price_id',
        'discount_coupon',
        'payable_price',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payable_price' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the cart entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the item associated with the cart entry.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the price associated with the cart entry.
     */
    public function price()
    {
        return $this->belongsTo(ItemPrice::class, 'price_id');
    }
}
