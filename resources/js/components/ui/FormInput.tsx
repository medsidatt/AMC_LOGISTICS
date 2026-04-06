import { clsx } from 'clsx';
import type { InputHTMLAttributes } from 'react';

interface FormInputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    wrapperClass?: string;
}

export default function FormInput({ label, error, wrapperClass, className, id, ...props }: FormInputProps) {
    const inputId = id ?? props.name;
    return (
        <div className={clsx('mb-4', wrapperClass)}>
            {label && (
                <label htmlFor={inputId} className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">
                    {label}
                    {props.required && <span className="text-[var(--color-danger)] ml-0.5">*</span>}
                </label>
            )}
            <input
                id={inputId}
                className={clsx(
                    'w-full px-3 py-2 rounded-lg border text-sm transition bg-[var(--color-surface)] text-[var(--color-text)] placeholder:text-[var(--color-text-muted)]',
                    'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]',
                    error ? 'border-[var(--color-danger)]' : 'border-[var(--color-border)]',
                    className,
                )}
                {...props}
            />
            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
