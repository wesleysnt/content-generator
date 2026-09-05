<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptVersion extends Model
{
    protected $fillable = ['prompt_template_id', 'version', 'content', 'is_active'];

    protected $casts = [
        'version' => 'integer',
        'is_active' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PromptTemplate::class, 'prompt_template_id');
    }
}
