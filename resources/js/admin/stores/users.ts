/**
 * Users store — first slice of the legacy Users Vuex module
 * (components/Users/store.js): the share-modal search users action.
 * The admin users list/roles pages port in their own slice.
 */
import { defineStore } from 'pinia';

import { api } from '@common/bootstrap';

export interface AppUser {
    id: number;
    hash: string;
    name: string;
    email: string;
    display_name?: string;
    avatar?: string;
    [key: string]: unknown;
}

export const useUsersStore = defineStore('users', {
    state: () => ({
        all: [] as AppUser[],
        roles: [] as Record<string, unknown>[],
        pagination: {} as Record<string, unknown>,
    }),

    actions: {
        /**
         * Legacy bug fixed (W4.23): the action resolved the {data}
         * wrapper, so the share modal's search dropdown stayed empty.
         */
        async searchUsers(params: { s: string }): Promise<AppUser[]> {
            const res = await api.get('/api/users/search', { params });

            return res.data.data as AppUser[];
        },
    },
});
