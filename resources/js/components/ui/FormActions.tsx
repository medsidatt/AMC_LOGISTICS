import Button from '@/components/ui/Button';

interface FormActionsProps {
    onCancel: () => void;
    /** Called when submit is clicked (drawer/overlay forms drive submit explicitly). */
    onSubmit?: () => void;
    submitLabel?: string;
    cancelLabel?: string;
    loading?: boolean;
    disabled?: boolean;
    /** 'submit' for native <form> footers, 'button' for explicit-handler overlays. */
    submitType?: 'button' | 'submit';
}

/**
 * Standard Cancel / Submit action pair, consolidating the footer markup duplicated
 * across every create/edit form. Renders just the two buttons so it drops straight
 * into a <Drawer footer> (which provides the flex container) or any form footer.
 */
export default function FormActions({
    onCancel,
    onSubmit,
    submitLabel = 'Enregistrer',
    cancelLabel = 'Annuler',
    loading = false,
    disabled = false,
    submitType = 'button',
}: FormActionsProps) {
    return (
        <>
            <Button variant="secondary" type="button" onClick={onCancel}>
                {cancelLabel}
            </Button>
            <Button
                type={submitType}
                onClick={submitType === 'button' ? onSubmit : undefined}
                loading={loading}
                disabled={disabled}
            >
                {submitLabel}
            </Button>
        </>
    );
}
