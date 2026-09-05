<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_variation_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('section_order');
            $table->string('heading');
            $table->longText('body');
            $table->timestamps();
            $table->unique(['content_variation_id', 'section_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_sections');
    }
};
