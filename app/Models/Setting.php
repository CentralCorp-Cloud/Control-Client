<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    public static function valueFor(string $key, mixed $default = null): mixed
    {
        return static::query()->whereKey($key)->value('value') ?? $default;
    }

    public static function boolean(string $key, bool $default = false): bool
    {
        return filter_var(static::valueFor($key, $default), FILTER_VALIDATE_BOOL);
    }
}
