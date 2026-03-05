<?php

declare(strict_types=1);

return [
    'channel' => 'backend',
    'level' => getenv('LOG_LEVEL') ?: 'debug',
];
