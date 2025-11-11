<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliveryCharge extends Model
{
    use HasFactory;

    const STATUS_PUBLISHED = 'published';
    const STATUS_UNPUBLISHED = 'unpublished';

    protected $fillable = [
        'city_id',
        'charge',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'charge' => 'decimal:2',
            'status' => 'string',
        ];
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
