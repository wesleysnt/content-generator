# System Architecture & Development Specification: Laravel AI Content Automation System

## 1. Project Overview & Objective

This document provides a comprehensive technical specification for building an **AI Content Automation System** using **Laravel**. The system acts as an internal admin dashboard for content writers. It accepts detailed topic briefs, queries the **OpenAI API** using **Structured Outputs**, generates multiple distinct content variations concurrently via background queues, allows writers to review/lock/edit variations, and dispatches finalized content to external websites via webhooks/APIs.

---

## 2. Technical Stack & Core Dependencies

* **Framework:** Laravel 11.x (PHP 8.2+)
* **Admin UI / Dashboard:** Laravel Filament 3.x (or Livewire 3 + Tailwind CSS) with TipTap / Rich-Text Editor integration.
* **AI Integration:** OpenAI API (specifically models supporting `json_schema` Structured Outputs, e.g., `gpt-4o` or `gpt-4o-mini`).
* **Queue Management:** Laravel Horizon with Redis driver for asynchronous, parallel generation jobs.
* **Database:** MySQL / PostgreSQL.

---

## 3. Core Architectural Decisions & Business Rules

1. **Single AI Provider:** OpenAI API is used for all text and metadata generation.
2. **Single-Pass Structured Output:** Each variation is generated in a single LLM request yielding a strictly typed JSON structure containing post title, slug, meta title, meta description, excerpt, HTML body content, and FAQ JSON schema.
3. **Multi-Variation Batch Generation:** The writer specifies the variation count ($N$). $N$ individual jobs are queued and processed in parallel. Each job is assigned a distinct "angle" (e.g., Educational/How-To, Data-Driven, Story-Driven/Case Study, Expert Analysis) with controlled temperature parameters ($0.7 - 0.85$) to guarantee distinct wording and concepts.
4. **No Image Generation (Phase 1):** Focus is strictly on written content and SEO. Optional image text prompts are stored in JSON for manual asset creation.
5. **Lock & Regenerate Matrix:** Writers can lock specific variations. When a writer requests regeneration for unlocked variations, the system uses the summaries/hooks of the *locked* variations as explicit negative context in the prompt ("Do not duplicate these concepts/hooks: [...]").
6. **External Publishing Integration:** Finalized content is exported out of the system to external target platforms (WordPress, Webflow, custom CMS) via webhook HTTP payloads or REST API integrations.

---

## 4. End-to-End System Workflow

### Step 1: Writer Brief Submission & Setup
* Writer fills out the creation form: **Topic/Title**, **Primary Keyword**, **Secondary Keywords**, **Tone**, **Target Persona**, and **Variation Count ($N$)**.
* System creates a `ContentRequest` record in `status = 'pending'`.

### Step 2: Parallel Batch Queue Execution
* System dispatches $N$ distinct `GenerateContentJob` tasks to Redis Queue.
* Each job calls OpenAI API with a unique angle/perspective and Structured Output schema enforcement.
* Completed jobs create linked `ContentVariation` records with token usage statistics.
* Request status transitions: `pending` → `processing` → `completed`.

### Step 3: Writer Review & Locking Matrix
* Writer views side-by-side or tabbed variation cards.
* **Actions per variation:**
  * **Lock/Save:** Toggles `is_locked = true` to protect from future batch wipes.
  * **Regenerate Unlocked:** Triggers a re-generation job for non-locked slots, injecting locked variation summaries as negative context.
  * **Discard:** Sets status to `discarded`.

### Step 4: Rich-Text Editing & SEO Audit
* Writer opens the selected variation in the Filament rich-text editor (TipTap).
* Real-time SEO widgets validate:
  * Meta Title length (50–60 characters).
  * Meta Description length (150–160 characters).
  * Primary/Secondary keyword density within HTML body.

### Step 5: External Webhook Export
* Writer clicks **Publish / Export**.
* System executes an asynchronous webhook job sending JSON payload (title, slug, meta tags, FAQ schema, full HTML body) to the external target API.

---

## 5. Database Schema & Models

### 5.1 Migration: `content_requests`
```php
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
            
            // Brief Inputs
            $table->string('topic');
            $table->string('primary_keyword');
            $table->json('secondary_keywords')->nullable();
            $table->string('tone')->default('professional');
            $table->string('target_persona')->nullable();
            $table->unsignedTinyInteger('variation_count')->default(3);
            
            // Status & Diagnostics
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
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
```

### 5.2 Migration: `content_variations`
```php
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
            
            // Variation Metadata
            $table->string('angle_type')->comment('e.g., Educational, Data-driven, Story-driven');
            
            // Generated Content Payload
            $table->string('title');
            $table->string('slug');
            $table->string('meta_title');
            $table->text('meta_description');
            $table->text('excerpt')->nullable();
            $table->longText('body_html');
            $table->json('faq_schema')->nullable();
            $table->json('image_prompts')->nullable();
            
            // Review & Lock Workflow
            $table->boolean('is_locked')->default(false);
            $table->enum('status', ['draft', 'approved', 'discarded', 'published'])->default('draft');
            
            // Token Usage & Audit
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->timestamp('published_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['content_request_id', 'status', 'is_locked']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_variations');
    }
};
```

### 5.3 Eloquent Model: `ContentRequest.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentRequest extends Model
{
    protected $fillable = [
        'user_id',
        'topic',
        'primary_keyword',
        'secondary_keywords',
        'tone',
        'target_persona',
        'variation_count',
        'status',
        'error_message',
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'variation_count' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ContentVariation::class);
    }

    public function lockedVariations(): HasMany
    {
        return $this->hasMany(ContentVariation::class)->where('is_locked', true);
    }
}
```

### 5.4 Eloquent Model: `ContentVariation.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentVariation extends Model
{
    protected $fillable = [
        'content_request_id',
        'angle_type',
        'title',
        'slug',
        'meta_title',
        'meta_description',
        'excerpt',
        'body_html',
        'faq_schema',
        'image_prompts',
        'is_locked',
        'status',
        'prompt_tokens',
        'completion_tokens',
        'published_at',
    ];

    protected $casts = [
        'faq_schema' => 'array',
        'image_prompts' => 'array',
        'is_locked' => 'boolean',
        'published_at' => 'datetime',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class, 'content_request_id');
    }
}
```

---

## 6. Implementation Guidelines for AI Coding Tools

### 6.1 OpenAI Structured Output Schema (JSON Schema)
When executing the OpenAI Chat Completion API (e.g., using `openai-php/client` or Laravel Http), pass `response_format` set to `json_schema` with the following strict structure:

```json
{
  "type": "json_schema",
  "json_schema": {
    "name": "content_generation_response",
    "strict": true,
    "schema": {
      "type": "object",
      "properties": {
        "title": { "type": "string" },
        "slug": { "type": "string" },
        "meta_title": { "type": "string" },
        "meta_description": { "type": "string" },
        "excerpt": { "type": "string" },
        "body_html": { "type": "string" },
        "faq_schema": {
          "type": "array",
          "items": {
            "type": "object",
            "properties": {
              "question": { "type": "string" },
              "answer": { "type": "string" }
            },
            "required": ["question", "answer"],
            "additionalProperties": false
          }
        },
        "image_prompts": {
          "type": "array",
          "items": { "type": "string" }
        }
      },
      "required": [
        "title",
        "slug",
        "meta_title",
        "meta_description",
        "excerpt",
        "body_html",
        "faq_schema",
        "image_prompts"
      ],
      "additionalProperties": false
    }
  }
}
```

### 6.2 Service Class: `OpenAIService.php`
Implement a dedicated service class `App\Services\OpenAIService` that:
1. Receives the `ContentRequest` parameters and target `angle_type`.
2. Receives any array of locked variation summaries to inject as negative prompt constraints (*"Do NOT use these hooks, titles, or narrative structures: [...]"*).
3. Calls OpenAI API with model `gpt-4o` using the strict `json_schema`.
4. Returns parsed JSON payload along with token count (`usage.prompt_tokens`, `usage.completion_tokens`).

### 6.3 Queue Job: `GenerateContentJob.php`
Implement `App\Jobs\GenerateContentJob` implementing `ShouldQueue`:
* Handle failures with retries (`$tries = 3`, exponential backoff).
* On successful API response, create a `ContentVariation` record linked to the `ContentRequest`.
* Update parent `ContentRequest` status to `completed` when all variations finish.

### 6.4 Webhook Job: `ExportContentToExternalCmsJob.php`
Implement an asynchronous job that sends a POST payload of the approved `ContentVariation` to the external target URL, handling HTTP timeouts and retry policies.

---

## 7. Instructions for AI Coding Assistant (Prompt Rules)
When implementing code based on this document:
1. Always follow standard Laravel 11 conventions and PHP 8.2+ strict typing.
2. Use Filament 3 resources and Livewire components for front-end admin interfaces.
3. Ensure all queue jobs handle API timeouts, exceptions, and update database status gracefully.
4. Keep OpenAI API key in `.env` (`OPENAI_API_KEY`) and load via `config/services.php`.
5. Maintain strict separation of concerns: Controller/Livewire → Jobs/Services → Database Models.
