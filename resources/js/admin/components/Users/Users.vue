<script setup lang="ts">
/**
 * Users page port (legacy Users.vue): toolbar + table + create-user
 * dialog. The beforeRouteEnter permission gate moved into route meta
 * (`user.view`, enforced by the router guard).
 */
import { ref } from 'vue';

import { hasPermission } from '@common/auth';

import UsersTable from './UsersTable.vue';
import UserForm from './UserForm.vue';

const openform = ref(false);
</script>

<template>
    <v-row>
        <v-col cols="12">
            <v-toolbar
                flat
                class="ub-h"
                color="secondary"
            >
                <v-toolbar-title>
                    <v-icon class="mr-1">mdi-people</v-icon>
                    Users
                </v-toolbar-title>

                <v-spacer />

                <v-btn
                    v-if="hasPermission('user.create')"
                    icon
                    @click="openform = true"
                >
                    <v-icon>mdi-account-plus</v-icon>
                </v-btn>

                <v-btn icon>
                    <v-icon>mdi-apps</v-icon>
                </v-btn>

                <v-btn icon>
                    <v-icon>mdi-refresh</v-icon>
                </v-btn>

                <v-btn icon>
                    <v-icon>mdi-dots-vertical</v-icon>
                </v-btn>
            </v-toolbar>
        </v-col>

        <v-col
            v-if="hasPermission('user.view')"
            cols="12"
        >
            <UsersTable />
        </v-col>

        <v-dialog
            v-if="hasPermission('user.create')"
            :model-value="openform"
            max-width="500px"
        >
            <UserForm @close="openform = false" />
        </v-dialog>
    </v-row>
</template>

<style>
.ub-h .v-toolbar__content {
    border-bottom: 1px solid #ddd;
}
</style>
