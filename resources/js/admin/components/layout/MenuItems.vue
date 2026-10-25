<script setup lang="ts">
/**
 * Side menu port (legacy layout/MenuItems.vue). Permission gating keeps
 * the legacy semantics via the migrated auth composable; icons are mdi-*
 * now that the material icon font strings are gone.
 */
import { computed } from 'vue';

import FavoriteFolders from './FavoriteFolders.vue';

import { hasPermission } from '@common/auth';

interface MenuItem {
    icon?: string;
    text?: string;
    name?: string;
    disabled?: boolean;
    permission?: boolean;
    divider?: boolean;
    heading?: string;
    favorite?: boolean;
}

const items = computed<MenuItem[]>(() => ([
    { icon: 'mdi-view-dashboard', text: 'Dashboard', name: 'dashboard', disabled: false, permission: true },
    { divider: true },
    { heading: 'Users', permission: true },
    { icon: 'mdi-account-multiple', text: 'Users', name: 'users', disabled: false },
    { icon: 'mdi-fingerprint', text: 'Roles', name: 'users-role', disabled: false, permission: hasPermission('role.view') },
    { divider: true },
    { favorite: true },
    { heading: 'My Drive', permission: true },
    { icon: 'mdi-folder-multiple-image', text: 'My Files', name: 'media', disabled: false },
    { icon: 'mdi-account-multiple', text: 'Shared with me', name: 'shared', disabled: false },
    { icon: 'mdi-star', text: 'Starred', name: 'starred', disabled: false },
    { icon: 'mdi-delete-forever', text: 'Trash', name: 'trash', disabled: false },
    { divider: true },
    { heading: 'Admin Settings', permission: true },
    { icon: 'mdi-translate', text: 'Translation', name: 'translation', disabled: false },
] as MenuItem[]).filter((i) => typeof i.permission === 'undefined' || i.permission));
</script>

<template>
    <v-list class="la-side-menu">
        <template
            v-for="(item, i) in items"
            :key="i"
        >
            <FavoriteFolders v-if="item.favorite" />

            <div
                v-else-if="item.heading"
                class="text-caption text-medium-emphasis px-4 pt-3"
            >
                {{ item.heading }}
            </div>

            <v-divider
                v-else-if="item.divider"
                class="my-3"
            />

            <v-list-item
                v-else
                :to="{ name: item.name }"
                :disabled="item.disabled"
            >
                <template #prepend>
                    <v-icon>{{ item.icon }}</v-icon>
                </template>

                <v-list-item-title>
                    {{ item.text }}
                </v-list-item-title>
            </v-list-item>
        </template>
    </v-list>
</template>
