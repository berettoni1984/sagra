<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $path
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Database\Factories\LogoFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereUpdatedAt($value)
 *
 * @property int $is_default
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Logo whereIsDefault($value)
 *
 * @mixin \Eloquent
 */
class Logo extends Model
{
    /** @use HasFactory<\Database\Factories\LogoFactory> */
    use HasFactory;

    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'path',
    ];

    /**
     * {@inheritDoc}
     */
    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
