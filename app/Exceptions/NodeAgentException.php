<?php

namespace App\Exceptions;

use RuntimeException;

class NodeAgentException extends RuntimeException
{
    public function __construct(public readonly string $agentCode, public readonly ?string $correlationId, public readonly int $httpStatus, string $message = 'Node Agent request failed', public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $httpStatus);
    }

    public function clientMessage(): string
    {
        return match ($this->agentCode) {
            'capacity_exceeded' => 'Nous ne disposons actuellement pas de capacité suffisante pour créer votre instance.',
            'rate_limited' => 'Le service est temporairement très sollicité. Réessayez dans quelques instants.',
            'conflict' => 'Une autre opération est déjà en cours sur ce CentralPanel.',
            'degraded' => 'Le Node est temporairement indisponible.',
            default => "L'opération n'a pas pu être réalisée. Réessayez plus tard ou contactez le support.",
        };
    }

    /** @return array<string, string|int|null> */
    public function context(): array
    {
        return [
            'agent_code' => $this->agentCode,
            'correlation_id' => $this->correlationId,
            'agent_http_status' => $this->httpStatus,
            'retry_after' => $this->retryAfter,
        ];
    }
}
