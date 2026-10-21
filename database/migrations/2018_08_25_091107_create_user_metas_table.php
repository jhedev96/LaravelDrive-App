<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_metas', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->index();
            $table->string('key');
            // legacy format: maybe_serialize()'d value
            $table->text('value');
            $table->boolean('hide')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_metas');
    }
};
