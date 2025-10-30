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
        Schema::create('food_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->date('consumed_on');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->string('serving_unit')->nullable();
            $table->decimal('calories', 8, 2);
            $table->decimal('protein_grams', 8, 2)->default(0);
            $table->decimal('carb_grams', 8, 2)->default(0);
            $table->decimal('fat_grams', 8, 2)->default(0);
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index(['user_id', 'consumed_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('food_entries');
    }
};
