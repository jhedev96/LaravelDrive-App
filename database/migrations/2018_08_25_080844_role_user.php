<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->integer('user_id')->index();
            $table->integer('role_id')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
