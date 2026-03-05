<?php

declare(strict_types=1);

return [
    'vsphere_api' => [
        'failure_threshold' => 5,
        'recovery_timeout' => 30,
        'success_threshold' => 3,
        'timeout' => 10,
    ],
    'outlook_graph_api' => [
        'failure_threshold' => 3,
        'recovery_timeout' => 60,
        'success_threshold' => 2,
        'timeout' => 15,
    ],
    'mysql' => [
        'failure_threshold' => 3,
        'recovery_timeout' => 10,
        'success_threshold' => 1,
        'timeout' => 5,
    ],
    'backend' => [
        'failure_threshold' => 5,
        'recovery_timeout' => 20,
        'success_threshold' => 3,
        'timeout' => 10,
    ],
];
