import { clsx } from 'clsx';

interface DateRangePickerProps {
    startDate: string;
    endDate: string;
    onStartChange: (value: string) => void;
    onEndChange: (value: string) => void;
    className?: string;
}

export default function DateRangePicker({ startDate, endDate, onStartChange, onEndChange, className }: DateRangePickerProps) {
    return (
        <div className={clsx('flex items-center gap-2', className)}>
            <div>
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Du</label>
                <input
                    type="date"
                    value={startDate}
                    onChange={(e) => onStartChange(e.target.value)}
                    className="px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                />
            </div>
            <div>
                <label className="block text-xs font-medium text-[var(--color-text-secondary)] mb-1.5">Au</label>
                <input
                    type="date"
                    value={endDate}
                    onChange={(e) => onEndChange(e.target.value)}
                    className="px-3 py-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] text-sm text-[var(--color-text)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]/20 focus:border-[var(--color-primary)] transition"
                />
            </div>
        </div>
    );
}
