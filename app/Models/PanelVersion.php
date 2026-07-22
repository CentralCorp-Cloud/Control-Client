<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelVersion extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'recommended' => 'boolean'];
    }
}
