import Select from './Select';

interface Option {
    value: string | number;
    label: string;
}

interface FormSelectProps {
    label?: string;
    options: Option[];
    value?: string | number | null;
    onChange: (value: string | number | null) => void;
    error?: string;
    required?: boolean;
    placeholder?: string;
    className?: string;
    wrapperClass?: string;
}

export default function FormSelect({ label, options, value, onChange, error, required, placeholder, className, wrapperClass }: FormSelectProps) {
    return (
        <div className={wrapperClass ?? 'mb-4'}>
            <Select
                label={label ? `${label}${required ? ' *' : ''}` : undefined}
                options={options}
                value={value}
                onChange={onChange}
                placeholder={placeholder}
                className={className}
            />
            {error && <p className="mt-1 text-xs text-[var(--color-danger)]">{error}</p>}
        </div>
    );
}
