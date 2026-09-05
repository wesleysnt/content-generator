<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentSection extends Model
{
    protected $fillable = ['content_variation_id', 'section_order', 'heading', 'body'];

    protected $casts = [
        'section_order' => 'integer',
    ];

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ContentVariation::class, 'content_variation_id');
    }
}
