/**
 * Root store — port of the legacy Vuex root (`store/index.js`).
 *
 * Fixes carried over: the legacy `isAuthenticated` getter read
 * `state.access_token`, an attribute that never existed in the state, so
 * the value was permanently `false`; the mutation flag is the real
 * source now (and the LD bootstrap user primes it).
 */
import { defineStore } from 'pinia';

import { api, readLdConfig } from '@common/bootstrap';

export interface Snackbar {
    show: boolean;
    message: string;
    color: string;
    status?: number;
}

export const useRootStore = defineStore('root', {
    state: () => ({
        isAuthenticated: readLdConfig().user !== null,
        snackbar: {
            show: false,
            message: '',
            color: 'success',
        } as Snackbar,
        status: '',
        hasLoadedOnce: false,
        stats: {
            files: null as number | null,
            folders: null as number | null,
            storage_bytes: null as number | null,
            users: null as number | null,
        },
    }),

    actions: {
        setSnackbar(payload: Partial<Snackbar>) {
            this.snackbar = {
                show: payload.show ?? true,
                message: payload.message ?? '',
                color: payload.color ?? 'success',
                status: payload.status,
            };
        },

        hideSnackbar() {
            this.snackbar.show = ! this.snackbar.show;
        },

        auth(authenticated: boolean) {
            this.isAuthenticated = authenticated;
        },

        async fetchStats(force = false) {
            if (this.stats.storage_bytes !== null && ! force) {
                return this.stats;
            }

            const res = await api.get('/api/stats');

            this.stats = res.data;

            return this.stats;
        },
    },
});
