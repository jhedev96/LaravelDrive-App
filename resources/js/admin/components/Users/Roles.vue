<script setup lang="ts">
/**
 * Roles page port (legacy Roles.vue): inline create form + role list.
 * The beforeRouteEnter role.view gate moved to route meta.
 */
import { useUsersStore } from '@ac/stores/users';

import { hasPermission } from '@common/auth';

import Role from './Role.vue';
import RoleForm from './RoleForm.vue';

import { storeToRefs } from 'pinia';

const usersStore = useUsersStore();
const { roles } = storeToRefs(usersStore);

void usersStore.getRoles();
</script>

<template>
    <v-row>
        <v-col
            cols="12"
            md="4"
        >
            <v-card flat>
                <v-toolbar
                    flat
                    color="secondary"
                >
                    <v-toolbar-title>Roles</v-toolbar-title>

                    <v-spacer />

                    <v-btn icon>
                        <v-icon>mdi-magnify</v-icon>
                    </v-btn>
                </v-toolbar>

                <RoleForm
                    v-if="hasPermission('role.create')"
                    :half-form="false"
                />

                <v-list
                    two-line
                    subheader
                >
                    <div
                        v-for="role in roles"
                        :key="role.id"
                    >
                        <Role :role="role" />
                    </div>
                </v-list>
            </v-card>
        </v-col>
    </v-row>
</template>
