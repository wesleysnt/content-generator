<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PromptTemplate extends Model
{
    protected $fillable = ['key', 'name', 'description'];

    public function versions(): HasMany
    {
        return $this->hasMany(PromptVersion::class)->orderByDesc('version');
    }

    public function activeVersion(): HasOne
    {
        return $this->hasOne(PromptVersion::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('is_active', true)
        );
    }
}
