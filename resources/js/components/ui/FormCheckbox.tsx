import { clsx } from 'clsx';
import type { InputHTMLAttributes } from 'react';

interface FormCheckboxProps extends Omit<InputHTMLAttributes<HTMLInputElement>, 'type'> {
    label: string;
    error?: string;
    wrapperClass?: string;
}

export default function FormCheckbox({ label, error, wrapperClass, className, id, ...props }: FormCheckboxProps) {
    const inputId = id ?? props.name;
    return (
        <div className={clsx('mb-4', wrapperClass)}>
            <label htmlFor={inputId} className="inline-flex items-center gap-2 cursor-pointer">
                <input
                    id={inputId}
                    type="checkbox"
                    className={clsx(
                        'w-4 h-4 rounded border-[var(--color-border)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]/20',
                        className,
                    )}
                    {...props}
                />
                <span className="text-sm text-[var(--color-text)]">{label}</span>
            </label>
            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
