<?php

declare(strict_types=1);

return [
    'app_name' => 'proCom Backend',
    'debug' => (bool)(getenv('APP_DEBUG') ?: false),
    'jwt_secret' => getenv('JWT_SECRET') ?: 'change-this-in-production',
    'jwt_expiry' => (int)(getenv('JWT_EXPIRY') ?: 3600),
    'upload_dir' => getenv('UPLOAD_DIR') ?: '/var/www/uploads',
    'tesseract_lang' => getenv('TESSERACT_LANG') ?: 'eng',
    'allowed_origins' => array_filter(explode(',', getenv('ALLOWED_ORIGINS') ?: '')),
    'public_routes' => [
        '/api/auth/login',
        '/api/auth/refresh',
        '/api/health',
        '/api/health/ready',
    ],
];
