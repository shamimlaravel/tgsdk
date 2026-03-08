<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_channels', function (Blueprint $table) {
            $table->increments('id');
            $table->string('channel_identifier')->unique();
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->unsignedBigInteger('total_files')->default(0);
            $table->unsignedBigInteger('total_bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_channels');
    }
};
