import { usePage } from '@inertiajs/react';

interface AuthProps {
    permissions?: string[];
    roles?: string[];
}

/**
 * Hook for checking the current user's permissions/roles inside React pages.
 *
 * Source of truth is the `auth` shared prop set in HandleInertiaRequests middleware:
 *   permissions = $user->getAllPermissions()->pluck('name')->toArray()
 *   roles       = $user->getRoleNames()->toArray()
 *
 * IMPORTANT: this is a UX-layer gate (hide buttons that would 403). The real
 * security boundary remains the controller middleware. Never trust the front
 * alone to enforce authorization.
 */
export function usePermission() {
    const { auth } = usePage().props as { auth?: AuthProps };
    const permissions = auth?.permissions ?? [];
    const roles = auth?.roles ?? [];

    const can = (perm: string | string[]): boolean => {
        if (Array.isArray(perm)) return perm.some((p) => permissions.includes(p));
        return permissions.includes(perm);
    };

    const cannot = (perm: string | string[]) => !can(perm);

    const hasRole = (role: string | string[]): boolean => {
        if (Array.isArray(role)) return role.some((r) => roles.includes(r));
        return roles.includes(role);
    };

    const isAdmin = hasRole(['Super Admin', 'Admin']);

    return { can, cannot, hasRole, isAdmin, permissions, roles };
}
