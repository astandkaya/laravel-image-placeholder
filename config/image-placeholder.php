<?php

return [
    'route_prefix' => env('PLACEHOLDER_PREFIX', 'placeholder'),
    'middleware' => ['web'],

    'driver' => env('PLACEHOLDER_DRIVER', 'gd'), // gd | imagick

    'max_width'  => 4096,
    'max_height' => 4096,
    'min_width'  => 1,
    'min_height' => 1,

    'random' => [
        'min_width'  => 64,
        'max_width'  => 2048,
        'min_height' => 64,
        'max_height' => 2048,
        'square'     => true,
        'cacheable'  => false,
    ],

    'default' => [
        'format'       => 'png', // png|jpg|webp
        'bg'           => '#cccccc',
        'fg'           => '#333333',
        'text'         => '{w}x{h}',
        'ttf_font'     => null,  // storage_path('app/fonts/...') など
        'font_size'    => null,  // null=自動（短辺/5）
        'jpeg_quality' => 90,
        'webp_quality' => 80,
    ],

    'allowed_formats' => ['png', 'jpg', 'jpeg', 'webp'],

    'cache' => [
        'etag'            => true,
        'browser_max_age' => 86400,
        'disk'            => false,
        'disk_path'       => storage_path('framework/cache/placeholder'),
        'disk_ttl'        => 7 * 24 * 3600,
    ],

    'debug' => [
        'enabled'        => env('APP_DEBUG', false),
        'query_key'      => 'debug',
        'meta_query_key' => 'meta',
        'headers'        => true,
        'overlay'        => false,
        'log'            => false,
        'event'          => true,
    ],
];
