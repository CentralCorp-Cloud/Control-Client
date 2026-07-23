<?php

namespace App\Services\Enrollment;

use App\Models\NodeEnrollment;
use App\Models\NodeEnrollmentIdempotency;

final class EnrollmentIdempotency
{
    public function replay(NodeEnrollment $enrollment, string $operation, string $key, array $request): ?array
    {
        $record = NodeEnrollmentIdempotency::where('node_enrollment_id', $enrollment->id)
            ->where('operation', $operation)->where('key', $key)->first();
        if (! $record) {
            return null;
        }
        if (! hash_equals($record->request_hash, $this->requestHash($request))) {
            throw new EnrollmentException('idempotency_conflict', 409, 'Idempotency key was used with another request.');
        }

        return ['status' => $record->response_status, 'body' => $record->response_body];
    }

    public function store(NodeEnrollment $enrollment, string $operation, string $key, array $request, int $status, array $response): array
    {
        NodeEnrollmentIdempotency::create([
            'node_enrollment_id' => $enrollment->id,
            'operation' => $operation,
            'key' => $key,
            'request_hash' => $this->requestHash($request),
            'response_status' => $status,
            'response_body' => $response,
        ]);

        return ['status' => $status, 'body' => $response];
    }

    private function requestHash(array $request): string
    {
        return hash('sha256', json_encode($request, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
