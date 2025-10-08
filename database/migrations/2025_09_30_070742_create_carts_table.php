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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // Gäste möglich
            $table->uuid('session_token')->nullable()->unique(); // für Gast-Cart (Cookie/LocalStorage)
            $table->boolean('is_active')->default(true); // active vs. converted/abandoned
            $table->string('status', 20)->default('active'); // optional: 'active','converted','abandoned'
            $table->timestamps();

            // „Ein aktiver Warenkorb pro User“ (MySQL-tauglich, kein Partial Index):
            $table->unique(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
