import { useEffect, useState } from 'react';
import { CheckCircle, XCircle, X } from 'lucide-react';
import { clsx } from 'clsx';

interface ToastProps {
    message: string;
    type: 'success' | 'error';
    onClose: () => void;
    duration?: number;
}

export default function Toast({ message, type, onClose, duration = 4000 }: ToastProps) {
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        const timer = setTimeout(() => {
            setVisible(false);
            setTimeout(onClose, 300);
        }, duration);
        return () => clearTimeout(timer);
    }, [duration, onClose]);

    return (
        <div
            className={clsx(
                'fixed bottom-6 right-6 z-[100] flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg transition-all duration-300 max-w-sm',
                visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                type === 'success' && 'bg-emerald-600 text-white',
                type === 'error' && 'bg-red-600 text-white',
            )}
        >
            {type === 'success' ? <CheckCircle size={20} /> : <XCircle size={20} />}
            <p className="text-sm font-medium flex-1">{message}</p>
            <button onClick={() => { setVisible(false); setTimeout(onClose, 300); }} className="opacity-70 hover:opacity-100">
                <X size={16} />
            </button>
        </div>
    );
}
