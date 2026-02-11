<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Logreader
    |--------------------------------------------------------------------------
    |
    | Control whether the logreader API is enabled or disabled.
    | Set to false to disable without removing the package.
    |
    */
    'enabled' => env('LOGREADER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | The token used to authenticate incoming API requests from the central
    | Logreader application. You receive this token when registering your
    | app in the Logreader dashboard.
    |
    */
    'token' => env('LOGREADER_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Log File Exclusions
    |--------------------------------------------------------------------------
    |
    | Configure which log files and folders should be excluded from the API.
    |
    | You can use:
    | - Exact filenames: 'sensitive.log'
    | - Folder paths: 'private/*' or 'sensitive'
    | - Wildcards: '*.tmp', 'cache*'
    |
    | Examples:
    | 'exclude_logs' => [
    |     'passwords.log',
    |     'credentials',
    |     'private/*',
    |     'cache*',
    | ]
    |
    */
    'exclude_logs' => array_filter(explode(',', env('LOGREADER_EXCLUDE_LOGS', ''))),
];
