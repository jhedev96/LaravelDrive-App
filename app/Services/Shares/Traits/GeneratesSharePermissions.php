<?php

namespace App\Services\Shares\Traits;

trait GeneratesSharePermissions
{
    /**
     * Generate permissions for a shared entry.
     *
     * @param  int|array  $permissions
     * @return array
     */
    protected function generateSharePermissions($permissions)
    {
        return [
            'view' => true,
            'edit' => $permissions == 1,
            'download' => $permissions == 2,
        ];
    }
}
