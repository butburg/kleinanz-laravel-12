<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ad_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->string('large_path');
            $table->string('large_thumb_path');
            $table->string('cropped_path')->nullable();
            $table->string('cropped_thumb_path')->nullable();
            $table->string('original_name');
            $table->unsignedTinyInteger('position');
            $table->boolean('is_title')->default(false);
            $table->timestamps();

            $table->index(['ad_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_images');
    }
};
