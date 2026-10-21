<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shareable_links', function (Blueprint $table) {
            $table->increments('id');
            $table->string('hash', 30)->unique();
            $table->integer('user_id')->unsigned()->index();
            $table->integer('file_id')->unsigned()->index();
            $table->boolean('allow_edit')->default(0);
            $table->boolean('allow_download')->default(1);
            $table->string('password')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shareable_links');
    }
};
