<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name')->index();
            $table->string('description')->nullable();
            $table->string('path')->nullable()->index();
            $table->string('type', 20)->nullable()->index();
            $table->string('public_path', 255)->nullable();
            $table->string('extension', 10)->nullable();
            $table->string('mime', 50)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_name', 255);

            $table->integer('parent_id')->nullable();
            $table->string('driver')->nullable();
            $table->string('driver_data')->nullable();
            $table->boolean('isdraft')->default(true);

            $table->integer('uploaded_by')->nullable();
            $table->integer('deleted_by')->nullable();
            $table->json('meta')->nullable();
            $table->json('permissions')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
