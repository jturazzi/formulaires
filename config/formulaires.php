<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration with email / password
    |--------------------------------------------------------------------------
    |
    | When disabled, only Microsoft 365 SSO can create accounts. Enable it
    | for deployments that do not use Azure AD / Entra ID.
    |
    */

    'registration_enabled' => env('REGISTRATION_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | File uploads from respondents
    |--------------------------------------------------------------------------
    |
    | Maximum size (in kilobytes) for a single uploaded file, and the list
    | of accepted extensions. Keep this list conservative: these files are
    | uploaded by anonymous visitors.
    |
    */

    'max_upload_kb' => env('FORM_MAX_UPLOAD_KB', 10240),

    'allowed_extensions' => [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt', 'csv', 'zip',
    ],

    /*
    |--------------------------------------------------------------------------
    | GDPR retention
    |--------------------------------------------------------------------------
    |
    | Default number of days responses (and their files) are kept before
    | the scheduled purge deletes them. Each form can override this value,
    | and an administrator can change the default from the admin panel.
    |
    */

    'default_retention_days' => env('DEFAULT_RETENTION_DAYS', 365),

];
