<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_user', function (Blueprint $table) {
            $table->integer('user_id')->unsigned()->index();
            $table->integer('file_id')->unsigned()->index();
            $table->boolean('owner')->default(0)->index();
            $table->text('permissions')->nullable();
            $table->timestamps();

            $table->unique(['file_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_user');
    }
};
