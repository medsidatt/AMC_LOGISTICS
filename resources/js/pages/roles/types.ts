import type { PermissionItem, PermissionMeta } from '@/components/permissions/PermissionMatrix';

export type { PermissionItem, PermissionMeta };

export interface Role {
    id: number;
    name: string;
    guard_name: string;
    permissions: PermissionItem[];
}

export type RolePaginator = {
    data: Role[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};
