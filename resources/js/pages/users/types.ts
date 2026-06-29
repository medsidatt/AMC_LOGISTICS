import type { PermissionMeta } from '@/components/permissions/PermissionMatrix';

export type { PermissionMeta };

export interface Role { id: number; name: string }
export interface Permission { id: number; name: string }

export interface User {
    id: number;
    name: string;
    email: string;
    is_suspended: boolean;
    roles: Role[];
    direct_permissions: string[];
    role_permissions: string[];
    created_at: string | null;
}

export type UserPaginator = {
    data: User[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
