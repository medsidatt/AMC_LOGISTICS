import { router } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import Modal from './Modal';
import Button from './Button';
import { useState } from 'react';

interface ConfirmDialogProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    message?: string;
    confirmLabel?: string;
    deleteUrl?: string;
    onConfirm?: () => void;
}

export default function ConfirmDialog({
    open, onClose, title = 'Confirmer la suppression', message = 'Cette action est irréversible.', confirmLabel = 'Supprimer', deleteUrl, onConfirm,
}: ConfirmDialogProps) {
    const [processing, setProcessing] = useState(false);

    const handleConfirm = () => {
        if (onConfirm) {
            onConfirm();
            onClose();
            return;
        }
        if (deleteUrl) {
            setProcessing(true);
            router.delete(deleteUrl, {
                onFinish: () => { setProcessing(false); onClose(); },
            });
        }
    };

    return (
        <Modal open={open} onClose={onClose} title={title} size="sm">
            <div className="text-center">
                <div className="w-14 h-14 rounded-full bg-red-100 dark:bg-red-900/20 flex items-center justify-center mx-auto mb-4">
                    <AlertTriangle size={28} className="text-[var(--color-danger)]" />
                </div>
                <p className="text-sm text-[var(--color-text-secondary)] mb-6">{message}</p>
                <div className="flex items-center justify-center gap-3">
                    <Button variant="secondary" onClick={onClose}>Annuler</Button>
                    <Button variant="danger" onClick={handleConfirm} loading={processing}>{confirmLabel}</Button>
                </div>
            </div>
        </Modal>
    );
}
