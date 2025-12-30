<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * Order status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COOKING = 'cooking';
    const STATUS_ON_THE_WAY = 'on_the_way';
    const STATUS_DELIVERED = 'delivered';

    /**
     * Payment status constants
     */
    const PAYMENT_STATUS_UNPAID = 'unpaid';
    const PAYMENT_STATUS_PAID = 'paid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'guest_id',
        'cart_id',
        'city_id',
        'state',
        'zip_code',
        'street_address',
        'total_amount',
        'status',
        'payment_status',
        'phone',
        'email',
        'name',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
        ];
    }

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the city for the order.
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the cart that was used for the order.
     */
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * Get the order items for the order.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
