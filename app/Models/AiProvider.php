<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Collection<int, AiModel> $models
 */
#[Fillable([
    'name',
    'slug',
    'adapter_type',
    'base_url',
    'auth_mode',
    'secret_ref',
    'options',
    'is_active',
])]
class AiProvider extends Model
{
    /**
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class);
    }

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'secret_ref' => 'encrypted',
        'options' => 'array',
        'is_active' => 'boolean',
    ];
}
