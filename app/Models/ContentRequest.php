<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentRequest extends Model
{
    protected $fillable = [
        'user_id', 'topic', 'primary_keyword', 'secondary_keywords', 'tone',
        'target_persona', 'target_word_count', 'variation_count',
        'additional_instructions', 'status', 'error_message',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected $casts = [
        'secondary_keywords' => 'array',
        'variation_count' => 'integer',
        'target_word_count' => 'integer',
        'status' => RequestStatus::class,
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

    public function unlockedVariations(): HasMany
    {
        return $this->hasMany(ContentVariation::class)->where('is_locked', false);
    }
}
