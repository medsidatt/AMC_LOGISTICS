import type { Page } from '@inertiajs/core';

export interface User {
    id: number;
    name: string;
    email: string;
}

export interface Auth {
    user: User | null;
    roles: string[];
    permissions: string[];
}

export interface Flash {
    success: string | null;
    error: string | null;
}

export interface SharedProps {
    auth: Auth;
    flash: Flash;
    locale: string;
    appName: string;
}

declare module '@inertiajs/react' {
    interface PageProps extends SharedProps {}
}
