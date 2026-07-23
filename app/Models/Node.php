<?php

namespace App\Models;

use App\Enums\NodeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $agent_node_id
 * @property string $endpoint
 * @property NodeStatus $status
 * @property Carbon|null $last_seen_at
 */
class Node extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = ['agent_token'];

    protected function casts(): array
    {
        return ['status' => NodeStatus::class, 'scheduling_enabled' => 'boolean', 'maintenance' => 'boolean', 'capabilities' => 'array', 'compatibility' => 'array', 'agent_token' => 'encrypted', 'agent_token_rotated_at' => 'datetime', 'last_seen_at' => 'datetime', 'last_error_at' => 'datetime', 'installed_at' => 'datetime'];
    }

    /** @return HasMany<Deployment, $this> */
    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    /** @return HasMany<AgentOperation, $this> */
    public function operations(): HasMany
    {
        return $this->hasMany(AgentOperation::class);
    }

    /** @return HasMany<NodeEnrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(NodeEnrollment::class);
    }

    public function isFresh(): bool
    {
        return $this->last_seen_at?->greaterThan(now()->subMinutes((int) config('centralcloud.nodes.offline_after_minutes', 35))) ?? false;
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->capabilities ?? [], true);
    }
}
