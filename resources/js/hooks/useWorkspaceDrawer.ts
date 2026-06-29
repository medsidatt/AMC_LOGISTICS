import { useEffect, useState } from 'react';

export type DrawerMode = 'none' | 'create' | 'view' | 'edit';

export interface WorkspaceDrawer {
    mode: DrawerMode;
    id: number | null;
}

interface NavOptions {
    /** Replace the history entry instead of pushing (use for save completions). */
    replace?: boolean;
}

export interface WorkspaceDrawerApi {
    drawer: WorkspaceDrawer;
    openCreate: (opts?: NavOptions) => void;
    openView: (id: number, opts?: NavOptions) => void;
    openEdit: (id: number, opts?: NavOptions) => void;
    close: (opts?: NavOptions) => void;
}

function parse(search: string): WorkspaceDrawer {
    const p = new URLSearchParams(search);
    if (p.get('create') === '1') return { mode: 'create', id: null };
    const view = p.get('view');
    if (view) return { mode: 'view', id: Number(view) };
    const edit = p.get('edit');
    if (edit) return { mode: 'edit', id: Number(edit) };
    return { mode: 'none', id: null };
}

/**
 * Generic URL-driven workspace drawer state machine — the platform standard
 * (docs/workspace-standard.md). Owns ONLY: URL parse/sync, the create/view/edit
 * modes, browser Back/Forward (popstate), refresh restoration, open/close
 * transitions, and the single-drawer guarantee. It carries NO business logic and
 * knows nothing about any module — callers pass a base path and receive the
 * current mode + id and navigation callbacks. Existing query params (e.g. ?page,
 * ?search) are preserved so paginated workspaces deep-link and refresh correctly.
 */
export function useWorkspaceDrawer(basePath: string): WorkspaceDrawerApi {
    const [search, setSearch] = useState<string>(() => (typeof window !== 'undefined' ? window.location.search : ''));

    useEffect(() => {
        const onPop = () => setSearch(window.location.search);
        window.addEventListener('popstate', onPop);
        return () => window.removeEventListener('popstate', onPop);
    }, []);

    const go = (d: WorkspaceDrawer, replace = false) => {
        const p = new URLSearchParams(window.location.search);
        p.delete('create');
        p.delete('view');
        p.delete('edit');
        if (d.mode === 'create') p.set('create', '1');
        else if (d.mode === 'view' && d.id != null) p.set('view', String(d.id));
        else if (d.mode === 'edit' && d.id != null) p.set('edit', String(d.id));

        const qs = p.toString();
        const url = basePath + (qs ? `?${qs}` : '');
        const state = window.history.state;
        if (replace) window.history.replaceState(state, '', url);
        else window.history.pushState(state, '', url);
        setSearch(window.location.search);
    };

    return {
        drawer: parse(search),
        openCreate: (opts) => go({ mode: 'create', id: null }, opts?.replace),
        openView: (id, opts) => go({ mode: 'view', id }, opts?.replace),
        openEdit: (id, opts) => go({ mode: 'edit', id }, opts?.replace),
        close: (opts) => go({ mode: 'none', id: null }, opts?.replace),
    };
}
