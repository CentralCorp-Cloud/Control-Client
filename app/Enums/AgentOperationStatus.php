<?php

namespace App\Enums;

enum AgentOperationStatus: string
{
    case Queued = 'QUEUED';
    case Running = 'RUNNING';
    case Succeeded = 'SUCCEEDED';
    case Failed = 'FAILED';

    public function terminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }
}
