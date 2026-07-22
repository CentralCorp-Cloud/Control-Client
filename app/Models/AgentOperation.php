<?php

namespace App\Models;

use App\Enums\AgentOperationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $idempotency_key
 * @property string $correlation_id
 * @property-read Deployment $deployment
 * @property-read Node $node
 */
class AgentOperation extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => AgentOperationStatus::class, 'started_at' => 'datetime', 'completed_at' => 'datetime', 'last_polled_at' => 'datetime'];
    }

    /** @return BelongsTo<Deployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    /** @return BelongsTo<Node, $this> */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /** @return HasOne<AgentRequest, $this> */
    public function request(): HasOne
    {
        return $this->hasOne(AgentRequest::class);
    }
}
