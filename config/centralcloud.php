<?php

return [
    'agent' => [
        'client_cert' => env('CENTRALCLOUD_AGENT_CLIENT_CERT'),
        'client_key' => env('CENTRALCLOUD_AGENT_CLIENT_KEY'),
        'ca_cert' => env('CENTRALCLOUD_AGENT_CA_CERT'),
        'allowed_cidrs' => array_values(array_filter(array_map('trim', explode(',', (string) env('CENTRALCLOUD_AGENT_ALLOWED_CIDRS', ''))))),
        'allowed_ports' => array_map('intval', explode(',', (string) env('CENTRALCLOUD_AGENT_ALLOWED_PORTS', '443,9443'))),
        'connect_timeout' => (int) env('CENTRALCLOUD_AGENT_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('CENTRALCLOUD_AGENT_TIMEOUT', 20),
    ],
    'panel' => [
        'image' => env('CENTRALPANEL_IMAGE'),
        'domain_suffix' => env('CENTRALPANEL_DOMAIN_SUFFIX', 'panels.centralcloud.fr'),
        'reserved_subdomains' => ['admin', 'api', 'app', 'assets', 'billing', 'dashboard', 'mail', 'status', 'support', 'www'],
        'health_path' => env('CENTRALPANEL_HEALTH_PATH', '/up'),
        'health_timeout' => (int) env('CENTRALPANEL_HEALTH_TIMEOUT', 60),
    ],
    'nodes' => [
        'offline_after_minutes' => (int) env('CENTRALCLOUD_NODE_OFFLINE_AFTER', 35),
        'ram_margin_bytes' => (int) env('CENTRALCLOUD_SCHEDULER_RAM_MARGIN', 536870912),
        'disk_margin_bytes' => (int) env('CENTRALCLOUD_SCHEDULER_DISK_MARGIN', 5368709120),
        'maximum_deployments' => (int) env('CENTRALCLOUD_NODE_MAXIMUM_DEPLOYMENTS', 50),
    ],
    'billing' => ['suspension_grace_days' => (int) env('CENTRALCLOUD_PAYMENT_GRACE_DAYS', 7)],
    'webcron' => ['username' => env('CENTRALCLOUD_CRON_USERNAME', 'centralcloud-cron'), 'secret' => env('CENTRALCLOUD_CRON_SECRET')],
];
