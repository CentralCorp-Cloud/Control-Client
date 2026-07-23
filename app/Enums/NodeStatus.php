<?php

namespace App\Enums;

enum NodeStatus: string
{
    case Provisioning = 'PROVISIONING';
    case Validating = 'VALIDATING';
    case Ready = 'READY';
    case Online = 'ONLINE';
    case Degraded = 'DEGRADED';
    case Offline = 'OFFLINE';
    case Maintenance = 'MAINTENANCE';
}
