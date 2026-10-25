<script setup lang="ts">
/**
 * Users table port (legacy UsersTable.vue). Fixes carried over:
 * - `activation` gated on permission 'user.updata' (a typo — nothing
 *   grants or matches it) so the status chip was dead for everyone;
 *   now 'user.update' (W4.28)
 * - the data-table footer pagination event was never wired, page
 *   changes never refetched (W4.29) — update:page pushes the route
 *   (the page watcher reloads it)
 */
import { ref, watch } from 'vue';

import type { AppUser } from '@ac/stores/users';

import { useUsersStore } from '@ac/stores/users';

import { currentUser, hasPermission } from '@common/auth';

import { useRoute, useRouter } from 'vue-router';

import { storeToRefs } from 'pinia';

import UserForm from './UserForm.vue';

const usersStore = useUsersStore();
const { all: users } = storeToRefs(usersStore);
const route = useRoute();
const router = useRouter();

const search = ref('');
const selected = ref<AppUser[]>([]);
const editUser = ref<Partial<AppUser>>({});
const openEditUserForm = ref(false);

const headers = [
    { title: 'Avatar', key: 'avatar', sortable: false },
    { title: 'First Name', align: 'start' as const, sortable: true, key: 'firstname' },
    { title: 'Last Name', key: 'lastname', align: 'start' as const },
    { title: 'Email', key: 'email', align: 'start' as const },
    { title: 'Role', key: 'role', align: 'start' as const },
    { title: 'Status', align: 'start' as const, key: 'status' },
    { title: 'Action', align: 'start' as const, key: 'action' },
];

// re-mounted per route page; load initial + on page changes
void usersStore.getUsers({ page: Number(route.query.page ?? 1) });

watch(() => route.query.page, (page) => {
    void usersStore.getUsers({ page: Number(page ?? 1) });
});

function editUserMethod(user: AppUser): void {
    const index = users.value.findIndex((u) => u.id === user.id);

    if (index !== -1) {
        editUser.value = users.value[index];
        openEditUserForm.value = true;
    }
}

function deleteUser(user: AppUser): void {
    if (confirm('Are you sure you want to delete this item?')) {
        void usersStore.deleteUser(user.id);
    }
}

function activation(user: AppUser & { status?: string }): void {
    if (! hasPermission('user.update')) {
        return;
    }

    user.status = String(user.status ?? '').toLowerCase() === 'inactive' ? 'active' : 'inactive';

    void usersStore.updateUser(user as never);
}

function getRandomColor(): Record<string, string> {
    const h = Math.floor(Math.random() * 360);
    const s = Math.floor(Math.random() * 100) + '%';
    const l = Math.floor(Math.random() * 60) + '%';

    return { backgroundColor: `hsl(${h}, ${s}, ${l})` };
}
</script>

<template>
    <v-card flat>
        <v-card-text class="d-flex align-center">
            <v-select
                :items="['Delete']"
                label="Bulk Action"
                variant="solo"
                flat
                hide-details
                class="mr-4"
            />
            <v-spacer />
            <v-text-field
                v-model="search"
                label="Search"
                single-line
                hide-details
            />
        </v-card-text>

        <v-data-table
            v-model="selected"
            :headers="headers"
            :items="users"
            :search="search"
            item-key="id"
            show-select
            show-current-page
            class="elevation-1"
            @update:page="(p: number) => router.push({ name: 'users', query: { page: p } })"
        >
            <template #item.avatar="{ item }">
                <v-avatar
                    v-if="item.avatar"
                    size="32"
                    color="grey lighten-4"
                >
                    <img
                        :src="(item.avatar as string) ?? ''"
                        :alt="(item.firstname as string) ?? ''"
                    >
                </v-avatar>
                <v-avatar
                    v-else
                    :style="getRandomColor()"
                    size="32"
                >
                    <span class="text-white">{{ String(item.name ?? 'u').charAt(0).toUpperCase() }}</span>
                </v-avatar>
            </template>

            <template #item.status="{ item }">
                <div class="text-center">
                    <v-chip
                        :class="item.status"
                        small
                        @click="activation(item)"
                    >
                        {{ item.status }}
                    </v-chip>
                </div>
            </template>

            <template #item.action="{ item }">
                <v-icon
                    v-if="hasPermission('user.update')"
                    small
                    class="mr-2"
                    @click="editUserMethod(item)"
                >
                    mdi-pencil
                </v-icon>
                <v-icon
                    v-if="hasPermission('user.delete') && (currentUser()?.id ?? -1) !== item.id"
                    small
                    @click="deleteUser(item)"
                >
                    mdi-delete
                </v-icon>
            </template>
        </v-data-table>

        <v-dialog
            v-if="hasPermission('user.update')"
            v-model="openEditUserForm"
            max-width="500px"
        >
            <UserForm
                :user="editUser"
                @close="openEditUserForm = false"
            />
        </v-dialog>
    </v-card>
</template>
