<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('cart_id')->nullable(); // optional, Referenz auf den Cart
            $table->string('email')->nullable();
            $table->integer('total_cents');
            $table->char('currency', 3)->default('eur');
            $table->string('status')->default('paid'); // paid, failed, pending ...
            $table->string('stripe_session_id')->unique();
            $table->string('stripe_payment_intent')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->integer('unit_price_cents');
            $table->integer('quantity');
            $table->integer('total_cents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
