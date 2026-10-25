/**
 * Admin SPA routes — the 13 legacy route files authored the maps in
 * components(*route.js) of the legacy tree; module views land
 * slice-by-slice; not-yet-ported views mount the module placeholder.
 */
import type { RouteRecordRaw } from 'vue-router';

export default [
    // Dashboard
    {
        path: 'dashboard',
        name: 'dashboard',
        component: () => import('@ac/components/Dashboard/Dashboard.vue'),
    },

    // My Drive
    {
        path: 'media',
        name: 'media',
        component: () => import('@ac/components/MyDrive/MyDrive.vue'),
    },
    {
        path: 'media/folder/:folderId',
        name: 'singleFolder',
        component: () => import('@ac/components/MyDrive/MyDrive.vue'),
    },
    {
        path: 'media/starred',
        name: 'starred',
        component: () => import('@ac/components/MyDrive/Stared.vue'),
    },
    {
        path: 'media/trash',
        name: 'trash',
        component: () => import('@ac/components/MyDrive/Trash.vue'),
    },
    {
        path: 'media/trash/:folderId',
        name: 'trashFolder',
        component: () => import('@ac/components/MyDrive/Trash.vue'),
    },
    {
        path: 'media/shared',
        name: 'shared',
        component: () => import('@ac/components/MyDrive/SharedWithMe.vue'),
    },
    {
        path: 'media/shared/:folderId',
        name: 'sharedFolder',
        component: () => import('@ac/components/MyDrive/SharedWithMe.vue'),
    },

    // Translation
    {
        path: 'translation',
        name: 'translation',
        component: () => import('@ac/components/Translation/Placeholder.vue'),
    },

    // Users
    {
        path: 'users',
        name: 'users',
        component: () => import('@ac/components/Users/Placeholder.vue'),
    },
    {
        path: 'users/roles',
        name: 'users-role',
        component: () => import('@ac/components/Users/Placeholder.vue'),
    },
    {
        path: 'users/roles/:id/permissions',
        name: 'role-permissions',
        component: () => import('@ac/components/Users/Placeholder.vue'),
    },
    {
        path: 'users/:id',
        name: 'users-profile',
        component: () => import('@ac/components/Users/Placeholder.vue'),
    },
] satisfies RouteRecordRaw[];
