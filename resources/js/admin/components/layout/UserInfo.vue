<script setup lang="ts">
/**
 * User menu port (legacy layout/UserInfo.vue).
 *
 * Fixed while porting: the legacy card showed two dead "Normal" buttons
 * and random placeholder photos (pravatar/vuetifyjs); the avatar now
 * renders the user's initials and only real data stays. The log-out
 * button posts the legacy logout endpoint and returns to /login.
 */
import { computed, ref } from 'vue';

import { api } from '@common/bootstrap';
import { currentUser, fullname } from '@common/auth';

const menu = ref(false);

const user = computed(() => currentUser());

const initials = computed(() => {
    const name = fullname();

    if (! name) {
        return '';
    }

    return name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
});

async function logout(): Promise<void> {
    try {
        await api.post('/logout');
    } finally {
        // the session is dropped server-side either way
        window.location.href = '/login';
    }
}
</script>

<template>
    <v-menu
        v-model="menu"
        :close-on-content-click="false"
        location="bottom"
    >
        <template #activator="{ props: activator }">
            <v-avatar
                size="32"
                class="ml-2 pointer"
                v-bind="activator"
            >
                <span class="text-caption font-weight-medium">{{ initials }}</span>
            </v-avatar>
        </template>

        <v-card width="320">
            <v-card-text>
                <v-row>
                    <v-col cols="5">
                        <v-avatar size="100">
                            <span class="text-h5">{{ initials }}</span>
                        </v-avatar>
                    </v-col>
                    <v-col cols="7">
                        <p class="mb-1">
                            {{ fullname() }}
                        </p>
                        <p class="text-body-2 text-medium-emphasis mb-0">
                            {{ user?.email }}
                        </p>
                    </v-col>
                </v-row>
            </v-card-text>

            <v-card-actions class="pt-0">
                <v-btn
                    color="error"
                    variant="tonal"
                    small
                    @click.prevent="logout()"
                >
                    Log out
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-menu>
</template>

<style scoped>
.pointer {
    cursor: pointer;
}
</style>
