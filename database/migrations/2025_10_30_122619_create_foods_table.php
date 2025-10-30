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
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->decimal('serving_size', 8, 2)->default(1);
            $table->string('serving_unit')->default('serving');
            $table->decimal('calories_per_serving', 8, 2);
            $table->decimal('protein_grams', 8, 2)->default(0);
            $table->decimal('carb_grams', 8, 2)->default(0);
            $table->decimal('fat_grams', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'barcode'])->whereNotNull('barcode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('foods');
    }
};
