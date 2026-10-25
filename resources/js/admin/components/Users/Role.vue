<script setup lang="ts">
/**
 * Role row port (legacy Role.vue): router link to the permissions
 * child page + inline edit form + delete.
 */
import { ref, type PropType } from 'vue';

import type { AppRole } from '@ac/stores/users';

import { useUsersStore } from '@ac/stores/users';

import { hasPermission } from '@common/auth';

import RoleForm from './RoleForm.vue';

const props = defineProps({
    role: {
        type: Object as PropType<AppRole>,
        required: true,
    },
});

const usersStore = useUsersStore();

const roleEdit = ref(false);

function deleteRole(id: number): void {
    void usersStore.deleteRole(id);
}
</script>

<template>
    <div>
        <v-list-item
            :to="{ name: 'role-permissions', params: { id: props.role.id } }"
            ripple
        >
            <v-list-item-title>{{ role.name }}</v-list-item-title>
            <v-list-item-subtitle>
                {{ role.description }}
            </v-list-item-subtitle>

            <template
                v-if="hasPermission('role.update') || hasPermission('role.delete')"
                #append
            >
                <v-icon
                    v-if="hasPermission('role.update')"
                    small
                    class="mr-2"
                    @click="roleEdit = ! roleEdit"
                >
                    mdi-pencil
                </v-icon>
                <v-icon
                    v-if="hasPermission('role.delete')"
                    small
                    @click="deleteRole(role.id)"
                >
                    mdi-delete
                </v-icon>
            </template>
        </v-list-item>
        <RoleForm
            v-if="roleEdit && hasPermission('role.update')"
            :role="role"
            @close="roleEdit = false"
        />
    </div>
</template>

<style>
.role-action {
    flex-direction: row;
    justify-content: flex-end;
    align-items: center;
}
</style>
