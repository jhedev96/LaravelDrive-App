/**
 * Current-user helpers ported from the legacy `mixin/index.js` (the
 * `@common/auth` mixin): the LD config supplies the authed user once per
 * page load, permissions ride along on the serialized user.
 */
import { readLdConfig } from './bootstrap';

export interface LdUser {
    id: number;
    name: string;
    email: string;
    firstname?: string;
    lastname?: string;
    permissions?: string[];
    role_id?: number;
    [key: string]: unknown;
}

export function currentUser(): LdUser | null {
    return readLdConfig().user as LdUser | null;
}

export function fullname(): string {
    const user = currentUser();

    if (! user) {
        return '';
    }

    if (user.firstname || user.lastname) {
        return `${user.firstname ?? ''} ${user.lastname ?? ''}`.trim();
    }

    return user.name;
}

export function getUserPermissions(): string[] {
    return currentUser()?.permissions ?? [];
}

export function hasPermission(permission: string): boolean {
    const permissions = getUserPermissions();

    if (! permissions.length) {
        return false;
    }

    if (permissions.includes('administrator')) {
        return true;
    }

    return permissions.includes(permission);
}
