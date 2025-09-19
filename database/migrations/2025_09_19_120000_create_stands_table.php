<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('location')->nullable(); // e.g. Hall A / B12
            $table->unsignedInteger('size_sqm')->nullable();
            $table->decimal('price_eur', 10, 2)->nullable();
            $table->boolean('is_reserved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stands');
    }
};
