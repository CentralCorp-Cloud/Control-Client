<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $node_id
 * @property-read Project $project
 * @property-read Node|null $node
 */
class Deployment extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['memory_bytes' => 'integer', 'cpu_limit' => 'float', 'provisioning_started_at' => 'datetime', 'deployed_at' => 'datetime', 'failed_at' => 'datetime', 'last_synced_at' => 'datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Node, $this> */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /** @return BelongsTo<PanelVersion, $this> */
    public function panelVersion(): BelongsTo
    {
        return $this->belongsTo(PanelVersion::class);
    }

    /** @return HasMany<AgentOperation, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(AgentOperation::class);
    }

    public function hasActiveOperation(): bool
    {
        return $this->operations()->whereIn('status', ['QUEUED', 'RUNNING'])->exists();
    }
}
