<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'cart_id',
        'email',
        'total_cents',
        'currency',
        'status',
        'stripe_session_id',
        'stripe_payment_intent'
    ];
    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
