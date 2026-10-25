/**
 * Shared bootstrap helpers for every entry (admin / auth / share).
 */
import axios, { type AxiosInstance } from 'axios';
import mitt from 'mitt';

/**
 * window.LD is written by the shell blades (csrf token + authed user).
 */
export interface LdConfig {
    csrfToken: string;
    user: null | {
        id: number;
        name: string;
        email: string;
        [key: string]: unknown;
    };
}

export function readLdConfig(): LdConfig {
    const ld = (window as unknown as { LD?: Partial<LdConfig> }).LD ?? {};

    return {
        csrfToken: ld.csrfToken ?? '',
        user: ld.user ?? null,
    };
}

/**
 * Legacy `window.Bus = new Vue()` → mitt (W5.3 mapping).
 */
export interface BusEvents {
    [key: string]: unknown;
}

export const bus = mitt<BusEvents>();

/**
 * CSRF-attached axios instance (legacy resources/assets behavior).
 */
export const api: AxiosInstance = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

api.interceptors.request.use((config) => {
    config.headers.set('X-CSRF-TOKEN', readLdConfig().csrfToken);

    return config;
});
