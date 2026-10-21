<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | "local" points at storage/app/uploads — the layout of the previous
    | LaravelDrive release is preserved (per-file "<file_name>/<name>" dirs).
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Supported drivers (Flysystem 3): local, ftp, sftp, s3 (+ community
    | dropbox adapter). The EOL rackspace disk from the previous release was
    | dropped.
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/uploads'),
            'url' => env('APP_URL').'/uploads',
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        'uploads_ftp' => [
            'driver' => 'ftp',
            'root' => env('UPLOADS_FTP_ROOT', '/'),
            'host' => env('UPLOADS_FTP_HOST'),
            'username' => env('UPLOADS_FTP_USER', ''),
            'password' => env('UPLOADS_FTP_PASSWORD', ''),
            'ssl' => env('UPLOADS_FTP_SSL', false),
            'port' => env('UPLOADS_FTP_PORT', 21),
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

        'uploads_digitalocean' => [
            'driver' => 's3',
            'key' => env('UPLOADS_DIGITALOCEAN_ACCESS_KEY'),
            'secret' => env('UPLOADS_DIGITALOCEAN_SECRET_KEY'),
            'region' => env('UPLOADS_DIGITALOCEAN_REGION', 'us-east-1'),
            'bucket' => env('UPLOADS_DIGITALOCEAN_BUCKET'),
            'endpoint' => env('UPLOADS_DIGITALOCEAN_ENDPOINT'),
            'visibility' => 'public',
        ],

        'uploads_dropbox' => [
            'driver' => 'dropbox',
            'token' => env('DROPBOX_TOKEN'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
