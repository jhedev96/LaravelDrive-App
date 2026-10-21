<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 8)->default('IDR');
            $table->string('status', 20)->default('pending')->index();
            // model: 'midtrans' | 'xendit' | 'admin' (manual activation)
            $table->string('gateway', 20)->nullable();
            $table->string('gateway_txn_id')->nullable()->index();
            // raw gateway payload (webhook body / snap result) for diagnostics
            $table->json('payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_orders');
    }
};
