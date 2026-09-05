<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RevisionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRevision extends Model
{
    protected $fillable = ['content_variation_id', 'snapshot', 'revision_type', 'created_by'];

    protected $casts = [
        'snapshot' => 'array',
        'revision_type' => RevisionType::class,
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ContentVariation::class, 'content_variation_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
