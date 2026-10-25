/**
 * Users store — port of the legacy Users Vuex module
 * (components/Users/store.js): users CRUD + roles + permissions.
 *
 * Fixed in port: the list pagination read `res.data.meta.pagination`
 * (W4.27 — the backend publishes {data,links,meta}, so page 2+ never
 * loaded); the plural roles getter action was misnamed `getRole`.
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
    permissions?: string[];
    [key: string]: unknown;
}

export interface AppRole {
    id: number;
    name: string;
    description?: string;
    [key: string]: unknown;
}

export const useUsersStore = defineStore('users', {
    state: () => ({
        all: [] as AppUser[],
        pagination: {} as Record<string, unknown>,
        permissions: [] as string[],
        roles: [] as AppRole[],
        errors: [] as string[],
    }),

    actions: {
        setErrors(errors: unknown) {
            this.errors = errors as string[];
        },

        /**
         * Legacy bug fixed (W4.23): the action resolved the {data}
         * wrapper for the share modal; returns the inner array.
         */
        async searchUsers(params: { s: string }): Promise<AppUser[]> {
            const res = await api.get('/api/users/search', { params });

            return res.data.data as AppUser[];
        },

        async getUsers(params: Record<string, unknown>) {
            const res = await api.get('/api/users', { params });

            this.all = res.data.data as AppUser[];
            this.pagination = res.data.meta;

            return res.data;
        },

        async addUser(params: Record<string, unknown>) {
            try {
                const res = await api.post('/api/users', params);

                this.all.unshift(res.data.data as AppUser);

                return res.data;
            } catch (error) {
                this.setErrors((error as { response?: { data?: unknown } }).response?.data ?? []);

                throw error;
            }
        },

        async updateUser(params: { id: number } & Record<string, unknown>) {
            const res = await api.put(`/api/users/${params.id}`, params);

            const i = this.all.findIndex((u) => u.id === res.data.data.id);

            if (i !== -1) {
                this.all[i] = res.data.data;
            }

            return res.data;
        },

        async deleteUser(id: number) {
            await api.delete(`/api/users/${id}`);

            const i = this.all.findIndex((u) => u.id === id);

            if (i !== -1) {
                this.all.splice(i, 1);
            }
        },

        async getPermissions() {
            const res = await api.get('/api/permissions');

            this.permissions = res.data.data as string[];

            return this.permissions;
        },

        async getRoles(params: Record<string, unknown> = {}) {
            const res = await api.get('/api/roles', { params });

            this.roles = res.data.data as AppRole[];

            return res.data;
        },

        async getRoleById(id: number) {
            const res = await api.get(`/api/roles/${id}`);

            return res.data as AppRole;
        },

        async addRole(params: Record<string, unknown>) {
            try {
                const res = await api.post('/api/roles', params);

                this.roles.unshift(res.data.data as AppRole);

                return res.data;
            } catch (error) {
                this.setErrors((error as { response?: { data?: unknown } }).response?.data ?? []);

                throw error;
            }
        },

        async updateRole(params: { id: number } & Record<string, unknown>) {
            const res = await api.put(`/api/roles/${params.id}`, params);

            const i = this.roles.findIndex((r) => r.id === res.data.data.id);

            if (i !== -1) {
                this.roles[i] = res.data.data;
            }

            return res.data;
        },

        async deleteRole(id: number) {
            await api.delete(`/api/roles/${id}`);

            const i = this.roles.findIndex((r) => r.id === id);

            if (i !== -1) {
                this.roles.splice(i, 1);
            }
        },
    },
});
