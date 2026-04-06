import { Eye, Pencil, Trash2 } from 'lucide-react';
import { clsx } from 'clsx';

interface ActionButtonsProps {
    onView?: () => void;
    onEdit?: () => void;
    onDelete?: () => void;
    viewHref?: string;
    editHref?: string;
    size?: 'sm' | 'md';
}

export default function ActionButtons({ onView, onEdit, onDelete, viewHref, editHref, size = 'sm' }: ActionButtonsProps) {
    const iconSize = size === 'sm' ? 14 : 16;
    const btnClass = clsx(
        'p-1.5 rounded-lg transition-colors',
        size === 'sm' ? 'p-1.5' : 'p-2',
    );

    return (
        <div className="flex items-center gap-1">
            {(onView || viewHref) && (
                viewHref ? (
                    <a href={viewHref} className={clsx(btnClass, 'text-[var(--color-info)] hover:bg-[var(--color-info)]/10')} title="Voir">
                        <Eye size={iconSize} />
                    </a>
                ) : (
                    <button onClick={onView} className={clsx(btnClass, 'text-[var(--color-info)] hover:bg-[var(--color-info)]/10')} title="Voir">
                        <Eye size={iconSize} />
                    </button>
                )
            )}
            {(onEdit || editHref) && (
                editHref ? (
                    <a href={editHref} className={clsx(btnClass, 'text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10')} title="Modifier">
                        <Pencil size={iconSize} />
                    </a>
                ) : (
                    <button onClick={onEdit} className={clsx(btnClass, 'text-[var(--color-primary)] hover:bg-[var(--color-primary)]/10')} title="Modifier">
                        <Pencil size={iconSize} />
                    </button>
                )
            )}
            {onDelete && (
                <button onClick={onDelete} className={clsx(btnClass, 'text-[var(--color-danger)] hover:bg-[var(--color-danger)]/10')} title="Supprimer">
                    <Trash2 size={iconSize} />
                </button>
            )}
        </div>
    );
}
