<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Config
 *
 * @property int $id
 * @property string $code
 * @property string $config_value
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereConfigValue($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereUpdatedAt($value)
 *
 * @property string|null $comment
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Config whereComment($value)
 *
 * @mixin \Eloquent
 */
class Config extends Model
{
    /**
     * {@inheritDoc}
     */
    protected $fillable = [
        'code',
        'config_value',
        'comment',
    ];
}
