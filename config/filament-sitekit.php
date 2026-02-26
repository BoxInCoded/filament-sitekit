<?php

declare(strict_types=1);

use BoxinCode\FilamentSiteKit\Connectors\GoogleAnalytics4Connector;
use BoxinCode\FilamentSiteKit\FilamentSiteKit;

return [
    'version' => FilamentSiteKit::VERSION,

    'license' => env('SITEKIT_LICENSE', 'free'),

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'scopes' => [
            'https://www.googleapis.com/auth/analytics.readonly',
            'https://www.googleapis.com/auth/webmasters.readonly',
            'openid',
            'email',
            'profile',
        ],
    ],

    'cache' => [
        'ttl_seconds' => 3600,
    ],

    'sync' => [
        'enabled' => true,
        'schedule' => 'hourly',
        'auto_schedule' => false,
        'queue' => 'default',
        'periods' => ['7d', '28d', '90d'],
    ],

    'tracking' => [
        'enabled' => env('SITEKIT_TRACKING_ENABLED', true),
        'method' => env('SITEKIT_TRACKING_METHOD', 'middleware'),
        'inject_only_if_missing' => env('SITEKIT_TRACKING_INJECT_ONLY_IF_MISSING', true),
        'exclude_paths' => [
            'admin*',
            'filament*',
            'livewire*',
            'api*',
        ],
    ],

    'branding' => [
        'enabled' => env('SITEKIT_BRANDING_ENABLED', false),
        'company_name' => env('SITEKIT_BRANDING_COMPANY_NAME', 'Website Report'),
        'logo_url' => env('SITEKIT_BRANDING_LOGO_URL'),
        'primary_color' => env('SITEKIT_BRANDING_PRIMARY_COLOR', '#111827'),
        'footer_text' => env('SITEKIT_BRANDING_FOOTER_TEXT'),
        'show_powered_by' => env('SITEKIT_BRANDING_POWERED_BY', true),
    ],

    'public' => [
        'path_prefix' => env('SITEKIT_PUBLIC_PREFIX', 'report'),
    ],

    'workspace' => [
        'resolver' => null,
    ],

    'filament' => [
        'admin_path_prefix' => 'admin',
    ],

    'encryption' => [
        'key_usage' => 'crypt',
    ],

    'connectors' => [
        'available' => [
            GoogleAnalytics4Connector::class,
        ],

        'enabled' => [
            'ga4' => true,
        ],

        'modules' => [
            'ga4' => [
                'title' => 'Analytics',
                'description' => 'Track users, sessions and page performance with Google Analytics 4.',
                'icon' => 'heroicon-o-chart-bar',
                'toggleable' => false,
            ],
        ],
    ],

    'authorization' => [
        'gate' => 'manageSiteKit',
    ],
];
