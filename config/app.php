<?php

use Illuminate\Support\Facades\Facade;

return [

    /**
     * Is app installed?
     */
    'installed' => env('APP_INSTALLED', false),

    /*
    |--------------------------------------------------------------------------
    | Cloud Version
    |--------------------------------------------------------------------------
    */
    'cloud' => env('APP_CLOUD', false),

    /*
    |--------------------------------------------------------------------------
    | Contributor Profile Links
    |--------------------------------------------------------------------------
    */
    'contributor_profile_links' => [
        'orcid' => [
            'meta' => 'orcid_url',
            'icon' => 'academicon-orcid',
            'label' => 'general.orcid_id',
            'color' => '#A1C837',
        ],
        'google_scholar' => [
            'meta' => 'google_scholar_url',
            'icon' => 'academicon-google-scholar',
            'label' => 'general.google_scholar',
            'color' => '#4185F4',
        ],
        'research_gate' => [
            'meta' => 'research_gate_url',
            'icon' => 'academicon-researchgate',
            'label' => 'general.research_gate',
            'color' => '#00CCBB',
        ],
        'scopus' => [
            'meta' => 'scopus_url',
            'icon' => 'academicon-scopus',
            'form_icon' => 'academicon-scopus-square',
            'label' => 'general.scopus_id',
            'color' => '#e9711c',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */

    'name' => env('APP_NAME', 'Leconfe'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | your application so that it is used when running Artisan tasks.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'asset_url' => env('ASSET_URL'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. We have gone
    | ahead and set this to a sensible default for you out of the box.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by the translation service provider. You are free to set this value
    | to any of the locales which will be supported by the application.
    |
    */

    'locale' => 'en',

    /**
     * Application Supported Locales
     */
    'locales' => [
        'an' => 'Aragonese (draft: incomplete)',
        'ar' => 'Arabic',
        'az' => 'Azerbaijani',
        'be' => 'Belarusian',
        'bg' => 'Bulgarian',
        'bs' => 'Bosnian',
        'bs_Latn' => 'Bosnian (Latin)',
        'ca' => 'Catalan',
        'ckb' => 'Central Kurdish',
        'cnr' => 'Montenegrin (draft: incomplete)',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'dsb' => 'Lower Sorbian (draft: incomplete)',
        'el' => 'Greek',
        'en' => 'English',
        'eo' => 'Esperanto',
        'es' => 'Spanish',
        'es_MX' => 'Spanish (Mexico)',
        'et' => 'Estonian',
        'eu' => 'Basque',
        'fa' => 'Persian',
        'fa_AF' => 'Persian (Afghanistan)',
        'fi' => 'Finnish',
        'fr' => 'French',
        'fr_CA' => 'French (Canada)',
        'gd' => 'Scottish Gaelic',
        'gl' => 'Galician',
        'he' => 'Hebrew',
        'hi' => 'Hindi',
        'hr' => 'Croatian',
        'hsb' => 'Upper Sorbian (draft: incomplete)',
        'hu' => 'Hungarian',
        'hy' => 'Armenian',
        'id' => 'Indonesian',
        'is' => 'Icelandic',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ka' => 'Georgian',
        'kab' => 'Kabyle (draft: incomplete)',
        'kk' => 'Kazakh',
        'ko' => 'Korean',
        'ky' => 'Kyrgyz',
        'lo' => 'Lao',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'mk' => 'Macedonian',
        'mn' => 'Mongolian',
        'mr' => 'Marathi',
        'ms' => 'Malay',
        'nb_NO' => 'Norwegian Bokmål (Norway)',
        'nl' => 'Dutch',
        'nn' => 'Norwegian Nynorsk',
        'pl' => 'Polish',
        'ps' => 'Pashto',
        'pt' => 'Portuguese',
        'pt_BR' => 'Portuguese (Brazil)',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'rue' => 'Rusyn (draft: incomplete)',
        'se' => 'Northern Sami (draft: incomplete)',
        'sid' => 'Sidamo (draft: incomplete)',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sq' => 'Albanian',
        'sr_Cyrl' => 'Serbian (Cyrillic)',
        'sr_Latn' => 'Serbian (Latin)',
        'sv' => 'Swedish',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        'uz_Latn' => 'Uzbek (Latin)',
        'vi' => 'Vietnamese',
        'zh_Hans' => 'Chinese (Simplified)',
        'zh_Hant' => 'Chinese (Traditional)',
    ],

    'rtl_locales' => ['ar', 'ckb', 'fa', 'fa_AF', 'he', 'ps', 'ur'],

    /*
    |--------------------------------------------------------------------------
    | Application Beacon
    |--------------------------------------------------------------------------
    |
    | This software will optionally provide Leconfe with a unique identifier for the
    | installation and other useful information. These are to be used for statistics and
    | security alert purposes only.
    |
    | If you do not want to send this information, disable by adding APP_BEACON = false to .env file.
    */
    'beacon' => env('APP_BEACON', true),

    /*
    |--------------------------------------------------------------------------
    | Application Fallback Locale
    |--------------------------------------------------------------------------
    |
    | The fallback locale determines the locale to use when the current one
    | is not available. You may change the value to correspond to any of
    | the language folders that are provided through your application.
    |
    */

    'fallback_locale' => 'en',

    /*
    |--------------------------------------------------------------------------
    | Faker Locale
    |--------------------------------------------------------------------------
    |
    | This locale will be used by the Faker PHP library when generating fake
    | data for your database seeds. For example, this will be used to get
    | localized telephone numbers, street address information and more.
    |
    */

    'faker_locale' => 'en_US',

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is used by the Illuminate encrypter service and should be set
    | to a random, 32 character string, otherwise these encrypted strings
    | will not be safe. Please do this before deploying an application!
    |
    */

    'key' => env('APP_KEY', 'base64:rrIGPj0tmmM/YxEshFHDg6Pr2cXPG0LMLFfjYKnjd+E='),

    'cipher' => 'AES-256-CBC',

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => 'file',
        // 'store'  => 'redis',
    ],

    /*
    |--------------------------------------------------------------------------
    | Autoloaded Service Providers
    |--------------------------------------------------------------------------
    |
    | The service providers listed here will be automatically loaded on the
    | request to your application. Feel free to add your own services to
    | this array to grant expanded functionality to your applications.
    |
    */

    'providers' => [

        /*
         * Laravel Framework Service Providers...
         */
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        /*
         * Package Service Providers...
         */

        /*
         * Application Service Providers...
         */
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\HorizonServiceProvider::class,
        App\Providers\RouteServiceProvider::class,
        App\Providers\PluginServiceProvider::class,
        App\Providers\PanelProvider::class,
        Filament\FilamentServiceProvider::class,
        App\Providers\FrontendServiceProvider::class,
        Rahmanramsi\LivewirePageGroup\LivewirePageGroupServiceProvider::class,
        Plank\Metable\MetableServiceProvider::class,
        // App\Providers\BroadcastServiceProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Aliases
    |--------------------------------------------------------------------------
    |
    | This array of class aliases will be registered when this application
    | is started. However, feel free to register as many as you wish as
    | the aliases are "lazy" loaded so they don't hinder performance.
    |
    */

    'aliases' => Facade::defaultAliases()->merge([
        // 'ExampleClass' => App\Example\ExampleClass::class,
        'MetaTag' => App\Facades\MetaTag::class,
        'Setting' => App\Facades\Setting::class,
    ])->toArray(),

    'must_verify_email' => env('MUST_VERIFY_EMAIL', false),

    'report_errors' => env('REPORT_ERRORS', false),

    'force_https' => env('APP_FORCE_HTTPS', false),

    'api_url' => env('LECONFE_API', 'https://panel.leconfe.com/api/'),
];
