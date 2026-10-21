<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive plan columns for the legacy users table (keeps the
     * upgrade-in-place path intact — see decision D15/D17).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('ip')->index();
            $table->timestamp('plan_expires_at')->nullable()->after('plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['plan_id']);
            $table->dropColumn('plan_expires_at');
            $table->dropColumn('plan_id');
        });
    }
};
