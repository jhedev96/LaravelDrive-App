<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taggables', function (Blueprint $table) {
            $table->integer('tag_id')->index();
            $table->integer('taggable_id')->index();
            $table->string('taggable_type')->index();
            // legacy: stored as string (kept for data compatibility)
            $table->string('user_id')->index()->nullable();

            $table->unique(['tag_id', 'taggable_id', 'user_id', 'taggable_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
    }
};
