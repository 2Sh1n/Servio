<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;

class Filters extends BaseConfig
{
    public $aliases = [
        'csrf'            => CSRF::class,
        'toolbar'         => DebugToolbar::class,
        'honeypot'        => Honeypot::class,

        'admin_sanitizer' => \App\Filters\AdminPanelSanitizer::class,
        'global_sanitizer'=> \App\Filters\GlobalSanitizer::class,
        'auth'            => \App\Filters\AuthFilter::class,
        'protected'       => \App\Filters\ProtectedRouteFilter::class,
        'ImageFallback'   => \App\Filters\ImageFallback::class,
        'language'        => \App\Filters\LanguageFilter::class,
        'output_escaper'  => \App\Filters\OutputEscaper::class,

        // IMPORTANT: Make sure this class exists and works
        'cors'            => \App\Filters\Cors::class,
    ];

    public array $globals = [

        /*
        |--------------------------------------------------------------------------
        | Filters applied BEFORE controller execution
        |--------------------------------------------------------------------------
        */
        'before' => [

            // CORS MUST BE FIRST
            'cors' => [],

            // Global sanitizer (excluding API routes)
            'global_sanitizer' => [
                'except' => [
                    'login/*',
                    'logout/*',
                    'api/*',
                    'partner/api/*',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Filters applied AFTER controller execution
        |--------------------------------------------------------------------------
        */
        'after' => [

            'ImageFallback',

            'toolbar' => [
                'except' => [
                    'api/webhooks/*',
                ],
            ],

            // DO NOT PUT CORS HERE
        ],
    ];

    public $methods = [];

    public $filters = [];
}