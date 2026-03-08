<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_files', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('disk_path')->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->enum('status', ['pending', 'uploading', 'available', 'failed'])->default('pending');
            $table->string('channel_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('file_id')->nullable();
            $table->string('file_unique_id')->nullable();
            $table->boolean('is_chunked')->default(false);
            $table->unsignedInteger('chunk_count')->default(1);
            $table->string('download_token')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('channel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_files');
    }
};
