import { useMemo } from 'react';
import { clsx } from 'clsx';

export interface PermissionItem {
    id: number;
    name: string;
}

export interface PermissionMeta {
    groups: Record<string, string[]>;
    labels: Record<string, string>;
}

interface Props {
    allPermissions: PermissionItem[];
    /** Currently selected permission ids (toggleable). */
    selected: number[];
    onChange: (ids: number[]) => void;
    meta: PermissionMeta;
    /** Permission ids shown checked + disabled (e.g. inherited from a role). */
    lockedIds?: number[];
    disabled?: boolean;
}

/**
 * Grouped, French-labelled permission checkboxes with a per-group "select all".
 * Used by the role editor and the per-user access editor.
 */
export default function PermissionMatrix({ allPermissions, selected, onChange, meta, lockedIds = [], disabled }: Props) {
    const byName = useMemo(() => {
        const m: Record<string, PermissionItem> = {};
        allPermissions.forEach((p) => { m[p.name] = p; });
        return m;
    }, [allPermissions]);

    const lockedSet = useMemo(() => new Set(lockedIds), [lockedIds]);

    // Build ordered groups from meta; collect anything unmapped into "Autres".
    const groups = useMemo(() => {
        const out: { label: string; perms: PermissionItem[] }[] = [];
        const seen = new Set<string>();
        for (const [label, codes] of Object.entries(meta.groups)) {
            const perms = codes.map((c) => byName[c]).filter(Boolean) as PermissionItem[];
            perms.forEach((p) => seen.add(p.name));
            if (perms.length) out.push({ label, perms });
        }
        const rest = allPermissions.filter((p) => !seen.has(p.name));
        if (rest.length) out.push({ label: 'Autres', perms: rest });
        return out;
    }, [allPermissions, byName, meta.groups]);

    const isSelected = (id: number) => selected.includes(id) || lockedSet.has(id);

    const toggle = (id: number) => {
        if (disabled || lockedSet.has(id)) return;
        onChange(selected.includes(id) ? selected.filter((x) => x !== id) : [...selected, id]);
    };

    const toggleGroup = (perms: PermissionItem[]) => {
        if (disabled) return;
        const ids = perms.filter((p) => !lockedSet.has(p.id)).map((p) => p.id);
        const allOn = ids.every((id) => selected.includes(id));
        onChange(allOn ? selected.filter((id) => !ids.includes(id)) : [...new Set([...selected, ...ids])]);
    };

    return (
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {groups.map((g) => (
                <div key={g.label} className="rounded-lg border border-[var(--color-border)] p-3">
                    <label className="flex items-center gap-2 mb-2 cursor-pointer">
                        <input
                            type="checkbox"
                            checked={g.perms.every((p) => isSelected(p.id))}
                            onChange={() => toggleGroup(g.perms)}
                            disabled={disabled}
                            className="rounded"
                        />
                        <span className="text-sm font-semibold text-[var(--color-text)]">{g.label}</span>
                    </label>
                    <div className="space-y-1.5 ml-1">
                        {g.perms.map((p) => {
                            const locked = lockedSet.has(p.id);
                            return (
                                <label
                                    key={p.id}
                                    className={clsx('flex items-center gap-2', locked || disabled ? 'cursor-default' : 'cursor-pointer')}
                                    title={locked ? 'Hérité du rôle' : undefined}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isSelected(p.id)}
                                        onChange={() => toggle(p.id)}
                                        disabled={disabled || locked}
                                        className="rounded"
                                    />
                                    <span className={clsx('text-xs', locked ? 'text-[var(--color-text-muted)]' : 'text-[var(--color-text-secondary)]')}>
                                        {meta.labels[p.name] ?? p.name}
                                        {locked && <span className="ml-1 text-[10px] opacity-70">(rôle)</span>}
                                    </span>
                                </label>
                            );
                        })}
                    </div>
                </div>
            ))}
        </div>
    );
}
