/**
 * Shared bootstrap helpers for every entry (admin / auth / share).
 */
import axios, { type AxiosInstance } from 'axios';
import mitt, { type EventType } from 'mitt';

declare global {
    interface Window {
        LD?: {
            csrfToken?: string;
            user?: unknown;
        };
    }
}

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
export type Bus = import('mitt').Emitter<Record<EventType, unknown>>;

export const bus = mitt<Record<EventType, unknown>>();

/**
 * CSRF-attached axios instance (legacy resources/assets behavior).
 */
export const api: AxiosInstance = axios.create({
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
});

api.interceptors.request.use((config) => {
    config.headers.set('X-CSRF-TOKEN', readLdConfig().csrfToken);

    const bearer = savedBearer();

    if (bearer) {
        config.headers.set('Authorization', `Bearer ${bearer}`);
    }

    return config;
});

/**
 * Bearer token storage — parity with the legacy common/auth.js
 * (localStorage `auth_token`, migrated to Storage proxy calls).
 */
export const TOKEN_KEY = 'auth_token';

export function savedBearer(): string {
    return window.localStorage.getItem(TOKEN_KEY) ?? '';
}

export function saveBearer(token: string): void {
    window.localStorage.setItem(TOKEN_KEY, token);
}

export function clearBearer(): void {
    window.localStorage.removeItem(TOKEN_KEY);
}

/**
 * W4.21: the legacy app only authenticated /api/* after a login THROUGH
 * the SPA (the bearer lived in localStorage). Every other session path
 * left the SPA with 401s on the whole API surface. This bridges it: an
 * existing token arms every request, otherwise the web session mints a
 * fresh Passport token via POST /spa-token (route is auth'd; the guest
 * failure is harmless and the SPA keeps its guarded behavior).
 */
export async function ensureApiToken(): Promise<string> {
    const existing = savedBearer();

    if (existing) {
        return existing;
    }

    if (! window.LD?.csrfToken) {
        return '';
    }

    try {
        const res = await axios.post('/spa-token', null, {
            headers: { 'X-CSRF-TOKEN': window.LD.csrfToken },
        });

        const bearer = String(res.data.token ?? '');

        if (bearer) {
            saveBearer(bearer);
        }

        return bearer;
    } catch {
        return '';
    }
}
