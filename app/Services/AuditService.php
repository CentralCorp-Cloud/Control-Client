<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

final class AuditService
{
    private const SENSITIVE = ['password', 'secret', 'token', 'key', 'authorization', 'credential', 'database_url'];

    public function record(string $action, ?Model $target = null, array $metadata = []): AuditLog
    {
        return AuditLog::create(['actor_id' => auth()->id(), 'action' => $action, 'target_type' => $target?->getMorphClass(), 'target_id' => $target?->getKey(), 'metadata' => $this->sanitize($metadata), 'ip_address' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 1000)]);
    }

    private function sanitize(array $data): array
    {
        foreach ($data as $key => $value) {
            if (collect(self::SENSITIVE)->contains(fn ($word) => str_contains(strtolower((string) $key), $word))) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitize($value);
            }
        }

        return $data;
    }
}
