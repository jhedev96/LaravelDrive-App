<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name', 40);
            $table->string('description')->nullable();
            // storage allowance per user, bytes
            $table->unsignedBigInteger('max_storage')->default(0);
            // one-period price (raw amount, e.g. IDR units) — exact meaning is handled by the gateway layer
            $table->unsignedBigInteger('price')->default(0);
            $table->string('currency', 8)->default('IDR');
            // how long a paid activation lasts; null = never expires (free)
            $table->unsignedInteger('duration_days')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
