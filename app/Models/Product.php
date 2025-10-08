<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use HasFactory;

    // Felder, die massenhaft zuweisbar sind
    protected $fillable = [
        'name',
        'sku',
        'price',
        'description',
        'image',
    ];
}
