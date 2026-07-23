<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\CertificateRequest;
use App\Http\Requests\Enrollment\CompletionRequest;
use App\Http\Requests\Enrollment\DeviceMetadataRequest;
use App\Http\Requests\Enrollment\ProgressRequest;
use App\Models\NodeEnrollment;
use App\Services\Enrollment\EnrollmentException;
use App\Services\Enrollment\NodeEnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class NodeEnrollmentController extends Controller
{
    public function __construct(private NodeEnrollmentService $service) {}

    public function device(DeviceMetadataRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->service->createDevice($request->validated()), 201);
    }

    public function poll(Request $request): JsonResponse
    {
        $request->validate(['device_code' => ['required', 'string', 'min:43', 'max:128']]);

        return $this->respond(fn () => $this->service->poll((string) $request->input('device_code')));
    }

    public function automatic(DeviceMetadataRequest $request): JsonResponse
    {
        return $this->respond(fn () => $this->service->exchangeAutomatic($this->bearer($request), $request->validated()));
    }

    public function progress(ProgressRequest $request, NodeEnrollment $enrollment): JsonResponse
    {
        return $this->respondResult(fn () => $this->service->progress($enrollment, $this->bearer($request), $this->idempotencyKey($request), $request->validated()));
    }

    public function certificate(CertificateRequest $request, NodeEnrollment $enrollment): JsonResponse
    {
        return $this->respondResult(fn () => $this->service->certificate($enrollment, $this->bearer($request), $this->idempotencyKey($request), $request->validated()));
    }

    public function complete(CompletionRequest $request, NodeEnrollment $enrollment): JsonResponse
    {
        return $this->respondResult(fn () => $this->service->complete($enrollment, $this->bearer($request), $this->idempotencyKey($request), $request->validated()));
    }

    private function bearer(Request $request): string
    {
        $token = $request->bearerToken();
        if (! $token || strlen($token) < 32) {
            throw new EnrollmentException('invalid_token', 401, 'Bearer token is missing or invalid.');
        }

        return $token;
    }

    private function idempotencyKey(Request $request): string
    {
        $key = (string) $request->header('Idempotency-Key');
        if (! Str::isUuid($key)) {
            throw new EnrollmentException('invalid_idempotency_key', 422, 'A UUID Idempotency-Key is required.');
        }

        return strtolower($key);
    }

    private function respond(callable $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json($callback(), $status, ['X-Correlation-ID' => request()->header('X-Correlation-ID', (string) Str::uuid())]);
        } catch (EnrollmentException $exception) {
            $headers = ['X-Correlation-ID' => request()->header('X-Correlation-ID', (string) Str::uuid())];
            if ($exception->retryAfter !== null) {
                $headers['Retry-After'] = (string) $exception->retryAfter;
            }

            return response()->json(['error' => $exception->errorCode, 'message' => $exception->getMessage(), 'correlation_id' => $headers['X-Correlation-ID']], $exception->httpStatus, $headers);
        }
    }

    private function respondResult(callable $callback): JsonResponse
    {
        try {
            $result = $callback();

            return response()->json($result['body'], $result['status'], ['X-Correlation-ID' => request()->header('X-Correlation-ID', (string) Str::uuid())]);
        } catch (EnrollmentException $exception) {
            $headers = ['X-Correlation-ID' => request()->header('X-Correlation-ID', (string) Str::uuid())];
            if ($exception->retryAfter !== null) {
                $headers['Retry-After'] = (string) $exception->retryAfter;
            }

            return response()->json(['error' => $exception->errorCode, 'message' => $exception->getMessage(), 'correlation_id' => $headers['X-Correlation-ID']], $exception->httpStatus, $headers);
        }
    }
}
