<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;

return [
    'default' => env('LOG_CHANNEL', 'json'),
    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/lumen.log'),
            'level' => 'debug',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'level' => env('APP_DEBUG', true) ? 'debug' : 'info',
            'days' => 14,
        ],
        'json' => [
            'driver' => 'daily',
            'path' => storage_path('logs/lumen.log'),
            'tap' => [App\Logging\JsonChannel::class],
            'level' => 'info',
            'days' => 1,
        ],
    ],
];
