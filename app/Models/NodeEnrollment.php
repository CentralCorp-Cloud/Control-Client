<?php

namespace App\Models;

use App\Enums\NodeEnrollmentMode;
use App\Enums\NodeEnrollmentStatus;
use App\Enums\NodeEnrollmentStep;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int|null $node_id
 * @property NodeEnrollmentStatus $status
 * @property NodeEnrollmentMode $mode
 * @property NodeEnrollmentStep $step
 * @property int $percentage
 * @property int $poll_interval
 * @property string|null $device_code_hash
 * @property string|null $user_code_hash
 * @property string|null $preauthorization_token_hash
 * @property string|null $bootstrap_token_hash
 * @property string|null $agent_fqdn
 * @property string|null $agent_endpoint
 * @property string|null $chosen_name
 * @property string|null $environment
 * @property string|null $region
 * @property string|null $requested_agent_version
 * @property string|null $installer_version
 * @property string|null $csr_hash
 * @property array<int, string>|null $allowed_source_cidrs
 * @property array<int, string>|null $allowed_client_sans
 * @property Carbon $expires_at
 * @property Carbon|null $bootstrap_expires_at
 * @property Carbon|null $last_polled_at
 * @property Carbon|null $bootstrap_token_delivered_at
 * @property Node|null $node
 */
class NodeEnrollment extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $hidden = [
        'user_code_hash', 'device_code_hash', 'preauthorization_token_hash',
        'bootstrap_token_hash', 'issued_certificate', 'issued_chain',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    protected function casts(): array
    {
        return [
            'status' => NodeEnrollmentStatus::class,
            'mode' => NodeEnrollmentMode::class,
            'step' => NodeEnrollmentStep::class,
            'ip_addresses' => 'array',
            'capabilities' => 'array',
            'allowed_source_cidrs' => 'array',
            'allowed_client_sans' => 'array',
            'initial_maintenance' => 'boolean',
            'completion_report' => 'array',
            'expires_at' => 'immutable_datetime',
            'bootstrap_expires_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'denied_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
            'revoked_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
            'last_polled_at' => 'immutable_datetime',
            'bootstrap_token_delivered_at' => 'immutable_datetime',
            'certificate_issued_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Node, $this> */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    /** @return BelongsTo<User, $this> */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    /** @return HasMany<NodeEnrollmentIdempotency, $this> */
    public function idempotencyRecords(): HasMany
    {
        return $this->hasMany(NodeEnrollmentIdempotency::class);
    }
}
