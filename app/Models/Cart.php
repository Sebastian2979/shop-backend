<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\CartItem;

class Cart extends Model
{
    protected $fillable = [
        'user_id',
        'session_token',
        'guest_token',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Beziehungen
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // Scopes
    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    // Service-ähnliche Helfer (Upsert)
    public function addItem(int $productId, int $quantity): CartItem
    {
        $item = $this->items()
            ->where('product_id', $productId)
            ->first();

        if ($item) {
            $item->quantity += $quantity;
            $item->save();
            return $item;
        }

        return $this->items()->create([
            'product_id'       => $productId,
            'quantity'              => $quantity,
        ]);
    }

    public function updateItemQty(int $cartItemId, int $quantity): void
    {
        $item = $this->items()->findOrFail($cartItemId);
        $item->quantity = $quantity;
        $item->save();
    }

    public function removeItem(int $cartItemId): void
    {
        $this->items()->whereKey($cartItemId)->delete();
    }
}
