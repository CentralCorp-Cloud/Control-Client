<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentRequest extends Model
{
    protected $guarded = [];

    protected $hidden = ['encrypted_payload', 'encrypted_headers'];

    protected function casts(): array
    {
        return ['last_attempted_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<AgentOperation, $this> */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(AgentOperation::class, 'agent_operation_id');
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
}
