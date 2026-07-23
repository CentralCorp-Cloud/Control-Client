<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeEnrollmentIdempotency extends Model
{
    protected $table = 'node_enrollment_idempotency';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['response_body' => 'array'];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(NodeEnrollment::class, 'node_enrollment_id');
    }
}
