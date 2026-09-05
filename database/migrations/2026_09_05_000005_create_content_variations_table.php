<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_variations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_request_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('variation_number');
            $table->string('angle_type');
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('focus_keyword')->nullable();
            $table->json('secondary_keywords')->nullable();
            $table->string('category')->nullable();
            $table->json('tags')->nullable();
            $table->string('og_title')->nullable();
            $table->string('og_description')->nullable();
            $table->json('faq')->nullable();
            $table->string('schema_type')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('is_locked')->default(false);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->string('model_used')->nullable();
            $table->foreignId('prompt_version_id')->nullable()->constrained()->nullOnDelete();
            $table->json('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['content_request_id', 'variation_number']);
            $table->index(['content_request_id', 'status', 'is_locked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_variations');
    }
};
