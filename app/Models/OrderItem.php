<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
  protected $fillable = ['order_id', 'product_id', 'name', 'unit_price_cents', 'quantity', 'total_cents'];
  public function order()
  {
    return $this->belongsTo(Order::class);
  }
}
