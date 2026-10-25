<script setup lang="ts">
/**
 * Share-file dialog port (legacy ShareFile.vue): user search combobox
 * (debounced), permission pick, and the shared-with list. Fixes: the
 * search action resolved the {data} wrapper so results never rendered
 * (W4.23) — resolved inner array now; the owner avatar referenced
 * uploader.avatar.avatar which no serializer produced — plain fields
 * render.
 */
import { computed, ref, watch } from 'vue';

import type { AppUser } from '@ac/stores/users';

import { useMediaStore, type MediaItem } from '@ac/stores/media';

import { useUsersStore } from '@ac/stores/users';

import { useRootStore } from '@ac/stores/root';

import { api } from '@common/bootstrap';

import { storeToRefs } from 'pinia';

interface SharedUser {
    id: number;
    display_name?: string;
    email?: string;
    avatar?: string;
}

const usersStore = useUsersStore();
const media = useMediaStore();
const root = useRootStore();

const { selectedFilesId, selectedMedia, shareFileModal } = storeToRefs(media);

const current = computed(() => selectedMedia.value as MediaItem);

const users = ref<AppUser[]>([]);
const people = ref<AppUser[]>([]);
const sharedwith = ref<SharedUser[]>([]);
const search = ref('');
const isLoading = ref(false);
const permission = ref(1);

const permissions = [
    {
        title: 'Can edit',
        id: 1,
        descrption: 'People can edit, delete and copy the item to their own drive.',
    },
    {
        title: 'Can download',
        id: 2,
        descrption: 'People can view and download the item.',
    },
    {
        title: 'Can view',
        id: 3,
        descrption: 'People can view the item.',
    },
];

let searchTimeout: ReturnType<typeof setTimeout> | undefined;

watch(search, (val) => {
    if (! val) {
        return;
    }

    isLoading.value = true;

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(async () => {
        people.value = await usersStore.searchUsers({ s: val });
        isLoading.value = false;
    }, 400);
});

function getUserList(): void {
    void api.get(`/api/shared/file/${String(selectedFilesId.value[0] ?? '')}/share-with`)
        .then((res: { data: { data: SharedUser[] } }) => {
            sharedwith.value = res.data.data;
        });
}

getUserList();

async function onSubmit(): Promise<void> {
    const userids = users.value
        .filter((u) => u?.id)
        .map((u) => u.id);

    const res = await api.post('/api/shares/add-users', {
        userIds: userids,
        fileids: selectedFilesId.value,
        permissions: permission.value,
    });

    root.setSnackbar({
        message: res.data.message,
        status: res.status,
        color: 'success',
        show: true,
    });

    getUserList();
    close();
}

function close(): void {
    media.shareFileModal = false;
}
</script>

<template>
    <v-dialog
        :model-value="shareFileModal"
        class="mpu"
        max-width="700"
        persistent
        @update:model-value="close()"
    >
        <v-card class="dl-share-modal">
            <v-card-title class="bg-grey-lighten-2">
                Share File
            </v-card-title>

            <v-card-text>
                <form
                    class="file-deselect"
                    @submit.prevent="onSubmit"
                >
                    <v-row align="center">
                        <v-col cols="8">
                            <v-combobox
                                v-model="users"
                                :items="people"
                                class="file-deselect"
                                :loading="isLoading"
                                v-model:search="search"
                                item-title="name"
                                item-value="id"
                                placeholder="Search User to Share"
                                multiple
                                chips
                                clearable
                                hide-details
                                closable-chips
                                return-object
                            >
                                <template #selection="{ item }">
                                    <v-chip
                                        closable
                                        :model-value="false"
                                        @click:close="users = users.filter((u) => u.id !== (item.raw as AppUser).id)"
                                    >
                                        <v-avatar class="bg-accent text-white text-caption">
                                            {{ String((item.raw as AppUser).name ?? '?').slice(0, 1).toUpperCase() }}
                                        </v-avatar>
                                        {{ (item.raw as AppUser).name }}
                                    </v-chip>
                                </template>
                            </v-combobox>
                        </v-col>

                        <v-col cols="2">
                            <v-menu
                                transition="slide-x-transition"
                                max-width="300"
                            >
                                <template #activator="{ props: menu }">
                                    <v-btn
                                        v-bind="menu"
                                        color="indigo"
                                        variant="outlined"
                                    >
                                        <v-icon>mdi-pencil</v-icon>
                                        <v-icon>mdi-chevron-down</v-icon>
                                    </v-btn>
                                </template>

                                <v-list class="file-deselect">
                                    <v-list-item
                                        v-for="item in permissions"
                                        :key="item.id"
                                        @click="permission = item.id"
                                    >
                                        <template #prepend>
                                            <v-icon v-if="permission === item.id">
                                                mdi-check-circle
                                            </v-icon>
                                        </template>

                                        <v-list-item-title>{{ item.title }}</v-list-item-title>
                                        <v-list-item-subtitle>{{ item.descrption }}</v-list-item-subtitle>
                                    </v-list-item>
                                </v-list>
                            </v-menu>

                            <v-btn
                                class="mt-2"
                                color="success"
                                type="submit"
                            >
                                Add
                            </v-btn>
                        </v-col>
                    </v-row>
                </form>

                <div
                    v-if="current.id"
                    class="owner-of-file mt-3"
                >
                    <div class="subheading">Owner of File</div>

                    <div class="inner">
                        <v-img
                            :src="current.uploader?.avatar ?? ''"
                            class="owner-avatar"
                        />
                        <div class="owen-name">
                            {{ current.uploader?.display_name ?? current.uploader?.name }} <br>
                            {{ current.uploader?.email }}
                        </div>
                    </div>

                    <v-divider />
                </div>

                <div
                    v-for="u in sharedwith"
                    :key="u.id"
                    class="owner-of-file mt-1"
                >
                    <div class="subheading">Share File with</div>

                    <div class="inner">
                        <div class="avatar-initial bg-secondary text-white">{{ String(u.display_name ?? u.email ?? '?').slice(0, 1).toUpperCase() }}</div>
                        <div class="owen-name">
                            {{ u.display_name }} <br>
                            {{ u.email }}
                        </div>
                    </div>
                </div>
            </v-card-text>
        </v-card>
    </v-dialog>
</template>

<style>
.dl-share-modal .v-combobox {
    border: 1px solid #ddd;
}

.inner {
    display: flex;
    justify-content: flex-start;
    margin-top: 20px;
}

.inner .v-image,
.inner .owner-avatar,
.avatar-initial {
    width: 40px;
    display: block;
    max-width: 40px;
    margin-right: 20px;
    border-radius: 50%;
    height: 40px;
    line-height: 40px;
    text-align: center;
}
</style>
