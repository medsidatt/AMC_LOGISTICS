import { clsx } from 'clsx';
import { useState, type InputHTMLAttributes } from 'react';

interface FormInputProps extends InputHTMLAttributes<HTMLInputElement> {
    label?: string;
    error?: string;
    wrapperClass?: string;
}

export default function FormInput({ label, error, wrapperClass, className, id, type, onWheel, onKeyDown, ...props }: FormInputProps) {
    const inputId = id ?? props.name;
    const isPassword = type === 'password';
    const isNumber = type === 'number';
    const [revealed, setRevealed] = useState(false);
    const effectiveType = isPassword ? (revealed ? 'text' : 'password') : type;

    // Numeric inputs must never change value by accident: block mouse-wheel and
    // up/down-arrow increments (spinner buttons are hidden via global CSS).
    const handleWheel = isNumber
        ? (e: React.WheelEvent<HTMLInputElement>) => { (e.target as HTMLInputElement).blur(); onWheel?.(e); }
        : onWheel;
    const handleKeyDown = isNumber
        ? (e: React.KeyboardEvent<HTMLInputElement>) => {
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') e.preventDefault();
            onKeyDown?.(e);
        }
        : onKeyDown;

    return (
        <div className={clsx('mb-4', wrapperClass)}>
            {label && (
                <label htmlFor={inputId} className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">
                    {label}
                    {props.required && <span className="text-[var(--color-danger)] ml-0.5">*</span>}
                </label>
            )}
            <div className={clsx(isPassword && 'relative')}>
                <input
                    id={inputId}
                    type={effectiveType}
                    onWheel={handleWheel}
                    onKeyDown={handleKeyDown}
                    className={clsx(
                        'w-full px-3 py-2 rounded-lg border text-sm transition bg-[var(--color-surface)] text-[var(--color-text)] placeholder:text-[var(--color-text-muted)]',
                        'focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)]',
                        error ? 'border-[var(--color-danger)]' : 'border-[var(--color-border)]',
                        isPassword && 'pr-10',
                        className,
                    )}
                    {...props}
                />
                {isPassword && (
                    <button
                        type="button"
                        onClick={() => setRevealed((v) => !v)}
                        tabIndex={-1}
                        aria-label={revealed ? 'Masquer le mot de passe' : 'Afficher le mot de passe'}
                        className="absolute inset-y-0 right-0 px-3 flex items-center text-[var(--color-text-muted)] hover:text-[var(--color-text)] transition"
                    >
                        {revealed ? (
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                            </svg>
                        ) : (
                            <svg className="w-4 h-4" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        )}
                    </button>
                )}
            </div>
            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
