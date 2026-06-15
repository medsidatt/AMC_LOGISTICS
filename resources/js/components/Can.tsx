import { type ReactNode } from 'react';
import { usePermission } from '@/hooks/usePermission';

interface CanProps {
    /** Any-of these permissions grants access. */
    permission?: string | string[];
    /** Any-of these roles grants access. Combined with `permission` via AND when both are set. */
    role?: string | string[];
    children: ReactNode;
    /** Rendered instead of children when the check fails. Defaults to nothing. */
    fallback?: ReactNode;
}

/**
 * Declarative permission gate for the UI.
 *
 *   <Can permission="truck-create"><Button>Ajouter</Button></Can>
 *   <Can permission={['driver-edit', 'driver-delete']}>...</Can>
 *   <Can role={['Admin', 'Super Admin']}>...</Can>
 *
 * UX-layer only: hides controls a user can't use. The controller middleware
 * remains the real security boundary — never rely on this alone.
 */
export default function Can({ permission, role, children, fallback = null }: CanProps) {
    const { can, hasRole } = usePermission();
    const passPerm = permission === undefined || can(permission);
    const passRole = role === undefined || hasRole(role);
    return passPerm && passRole ? <>{children}</> : <>{fallback}</>;
}
