<?php

namespace App\Enums;

enum NodeStatus: string
{
    case Online = 'ONLINE';
    case Degraded = 'DEGRADED';
    case Offline = 'OFFLINE';
    case Maintenance = 'MAINTENANCE';
}
