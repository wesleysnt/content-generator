<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AngleType;
use App\Enums\VariationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentVariation extends Model
{
    protected $fillable = [
        'content_request_id', 'variation_number', 'angle_type',
        'title', 'slug', 'excerpt', 'meta_title', 'meta_description',
        'focus_keyword', 'secondary_keywords', 'category', 'tags',
        'og_title', 'og_description', 'faq', 'schema_type',
        'status', 'is_locked', 'prompt_tokens', 'completion_tokens',
        'model_used', 'prompt_version_id', 'raw_response', 'error_message',
    ];

    protected $attributes = [
        'status' => 'pending',
        'is_locked' => false,
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'tags' => 'array',
        'faq' => 'array',
        'raw_response' => 'array',
        'is_locked' => 'boolean',
        'status' => VariationStatus::class,
        'angle_type' => AngleType::class,
        'variation_number' => 'integer',
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class, 'content_request_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(ContentSection::class)->orderBy('section_order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class)->latest();
    }

    public function promptVersion(): BelongsTo
    {
        return $this->belongsTo(PromptVersion::class);
    }

    public function isRegenerable(): bool
    {
        return ! $this->is_locked && $this->status !== VariationStatus::Discarded;
    }
}
