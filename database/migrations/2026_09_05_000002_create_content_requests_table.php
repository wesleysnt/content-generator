<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('topic');
            $table->string('primary_keyword');
            $table->json('secondary_keywords')->nullable();
            $table->string('tone')->default('professional');
            $table->string('target_persona')->nullable();
            $table->unsignedInteger('target_word_count')->default(1500);
            $table->unsignedTinyInteger('variation_count')->default(3);
            $table->text('additional_instructions')->nullable();
            $table->string('status')->default('draft');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_requests');
    }
};
