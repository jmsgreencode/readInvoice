<?php

declare(strict_types=1);

return [
    'base_url' => getenv('VSPHERE_URL') ?: 'https://vcenter.example.com',
    'user' => getenv('VSPHERE_USER') ?: '',
    'pass' => getenv('VSPHERE_PASS') ?: '',
    'ssl_verify' => (bool)(getenv('VSPHERE_SSL_VERIFY') ?? true),
];
