<script setup lang="ts">
/**
 * Layout port (legacy components/layout/Layout.vue), Vuetify 4 shape:
 * `$vuetify.breakpoint` became `display`, `VContent` became `v-main`,
 * the icon strings became mdi-*, and the snackbar/auth state moved from
 * Vuex mapState to the root Pinia store.
 */
import { computed, ref } from 'vue';

import Login from '@auth/components/Login.vue';

import MenuItems from './MenuItems.vue';
import UserInfo from './UserInfo.vue';

import { useRootStore } from '@ac/stores/root';

import { storeToRefs } from 'pinia';

const root = useRootStore();
const { snackbar } = storeToRefs(root);
const isAuthenticated = computed(() => root.isAuthenticated);

const drawer = ref<boolean | null>(null);

// the blocked-shell login dialog shows itself on every screen until the
// user authenticates, exactly like the legacy layout
const showLoginDialog = computed(() => ! isAuthenticated.value);
</script>

<template>
    <v-app id="ld">
        <v-navigation-drawer
            v-model="drawer"
            width="240"
            color="nav"
        >
            <MenuItems />
        </v-navigation-drawer>

        <v-app-bar
            flat
            color="header"
            class="la-header"
        >
            <v-app-bar-title class="ml-1 pl-3">
                <v-app-bar-nav-icon @click.stop="drawer = !drawer" />
                <span class="hidden-sm-and-down">
                    <img
                        class="ld-logo"
                        src="@ac/images/logo.png"
                        width="150px"
                        alt="LaravelDrive"
                    >
                </span>
            </v-app-bar-title>

            <v-text-field
                flat
                variant="solo"
                density="comfortable"
                hide-details
                prepend-inner-icon="mdi-magnify"
                placeholder="Search"
                class="hidden-sm-and-down la-search"
            />

            <v-spacer />

            <v-btn icon>
                <v-icon>mdi-apps</v-icon>
            </v-btn>

            <v-btn icon>
                <v-icon>mdi-bell</v-icon>
            </v-btn>

            <UserInfo v-if="isAuthenticated" />
        </v-app-bar>

        <v-main>
            <v-container>
                <RouterView v-slot="{ Component }">
                    <KeepAlive>
                        <component :is="Component" />
                    </KeepAlive>
                </RouterView>
            </v-container>

            <v-snackbar
                v-model="snackbar.show"
                :color="snackbar.color"
                location="bottom right"
                :timeout="6000"
            >
                {{ snackbar.message }}
                <v-btn
                    dark
                    @click="root.hideSnackbar()"
                >
                    Close
                </v-btn>
            </v-snackbar>

            <template v-if="showLoginDialog">
                <v-dialog
                    :model-value="showLoginDialog"
                    persistent
                    width="500"
                >
                    <Login />
                </v-dialog>
            </template>
        </v-main>
    </v-app>
</template>

<style>
.la-search input {
    margin-top: 0;
}

.ld-header .v-toolbar__content {
    color: white;
}

.ld-logo {
    vertical-align: middle;
}
</style>
