<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    protected $fillable = [
        'user_id', 'content_request_id', 'content_variation_id',
        'provider', 'model', 'operation', 'input_tokens', 'output_tokens',
        'total_tokens', 'estimated_cost', 'duration_ms', 'status', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'total_tokens' => 'integer',
        'estimated_cost' => 'float',
        'duration_ms' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ContentRequest::class, 'content_request_id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ContentVariation::class, 'content_variation_id');
    }
}
