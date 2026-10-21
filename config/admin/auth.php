<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication for the admin area
    |--------------------------------------------------------------------------
    |
    | This configuration is consumed by RoleController@getAbilities to expose
    | the "Super" permission bucket in the admin UI.
    |
    */

    'permissions' => [
        'super' => [
            'administrator',
        ],
        'guests' => [

        ],
    ],
];
