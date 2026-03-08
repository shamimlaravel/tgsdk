<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_file_chunks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('file_id');
            $table->unsignedInteger('chunk_index');
            $table->string('channel_id');
            $table->string('session_name')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('file_id_tg')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->boolean('is_compressed')->default(false);
            $table->string('encryption_iv')->nullable();
            $table->enum('status', ['pending', 'uploading', 'available', 'failed'])->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('file_id')
                ->references('id')
                ->on('telegram_files')
                ->cascadeOnDelete();

            $table->index(['file_id', 'chunk_index']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_file_chunks');
    }
};
