<?php

namespace App\Models;

use App\Enums\DomainMode;
use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $owner_id
 * @property ProjectStatus $status
 * @property-read User $owner
 * @property-read Plan $plan
 * @property-read Deployment|null $deployment
 * @property-read ProvisioningRequest|null $provisioningRequest
 */
class Project extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => ProjectStatus::class, 'domain_mode' => DomainMode::class, 'payment_confirmed_at' => 'datetime', 'domain_verified_at' => 'datetime', 'domain_last_checked_at' => 'datetime', 'suspended_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasOne<Deployment, $this> */
    public function deployment(): HasOne
    {
        return $this->hasOne(Deployment::class);
    }

    /** @return HasOne<ProvisioningRequest, $this> */
    public function provisioningRequest(): HasOne
    {
        return $this->hasOne(ProvisioningRequest::class);
    }

    public function isCustomDomain(): bool
    {
        return $this->getAttribute('domain_mode') === DomainMode::Custom;
    }

    public function publicHostname(): ?string
    {
        if ($this->isCustomDomain() && $this->domain_verified_at && $this->custom_hostname) {
            return $this->custom_hostname;
        }

        return $this->canonical_hostname ?: $this->deployment?->hostname;
    }
}
