'use client';

import {FormEvent, ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogSize,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button, type ButtonProps} from '@/components/ui/button';
import {toastError} from '@/lib/utils/errors';
import {
    hasUnsavedChanges,
    UnsavedChangesScope,
    useUnsavedChangesChildScope,
    useUnsavedChangesPrompt,
} from '@/lib/navigation/unsavedChanges';

export type FormDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    /**
     * Control rendered on the header row, opposite the title (a mode switch,
     * for instance). Not compatible with `description`.
     */
    headerEnd?: ReactNode;
    size?: DialogSize;
    className?: string;
    bodyClassName?: string;
    /** Rendered on the left of the footer, opposite the actions */
    footerStart?: ReactNode;
    submitLabel?: ReactNode;
    submitIcon?: ReactNode;
    submitVariant?: ButtonProps['variant'];
    cancelLabel?: ReactNode;
    /** Additional condition to enable the submit button */
    canSubmit?: boolean;
    /** Read-only dialog: no Cancel, the action button just closes */
    hideCancel?: boolean;
    /** Dialog with nothing to submit: only the Cancel button is rendered */
    hideSubmit?: boolean;
    /**
     * Whether the form holds changes worth protecting. When set, dismissing
     * the dialog (escape, click outside, close button) asks for confirmation
     * instead of silently dropping what the user typed. Forms inside the
     * dialog using `useDirtyState` are taken into account too.
     */
    dirty?: boolean;
    /**
     * Submit handler. The dialog disables its actions and shows a spinner
     * while it runs, closes on success and reports failures as a toast.
     * Return `false` to keep the dialog open.
     */
    onSubmit?: () => unknown | Promise<unknown>;
};

/**
 * Shell shared by every imperative form dialog: header, scrollable body,
 * cancel/submit footer, submit-on-Enter, pending state, error reporting and
 * the unsaved-changes guard.
 *
 * It only owns the chrome — the fields and their state stay in the caller.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    children,
    headerEnd,
    size = 'sm',
    className,
    bodyClassName,
    footerStart,
    submitLabel,
    submitIcon,
    submitVariant,
    cancelLabel,
    canSubmit = true,
    hideCancel,
    hideSubmit,
    dirty,
    onSubmit,
}: FormDialogProps) {
    const {t} = useTranslation();
    const [submitting, setSubmitting] = useState(false);
    const [confirmDiscard, setConfirmDiscard] = useState(false);
    const scope = useUnsavedChangesChildScope();
    // Leaving the page must not drop them either
    useUnsavedChangesPrompt(!!dirty && open);

    const close = () => {
        setConfirmDiscard(false);
        onOpenChange(false);
    };

    const requestClose = () => {
        if (submitting) {
            return;
        }
        if (dirty || hasUnsavedChanges(scope)) {
            setConfirmDiscard(true);

            return;
        }
        close();
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (!onSubmit) {
            close();

            return;
        }
        setSubmitting(true);
        try {
            if ((await onSubmit()) !== false) {
                onOpenChange(false);
            }
        } catch (err) {
            toastError(err);
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <>
            <Dialog open={open} onOpenChange={o => !o && requestClose()}>
                <DialogContent
                    size={size}
                    className={className}
                    data-testid="form-dialog"
                >
                    {/* `contents` keeps the dialog's own flex layout intact */}
                    <form onSubmit={submit} className="contents">
                        <DialogHeader
                            className={
                                headerEnd
                                    ? 'flex-row items-center justify-between pr-8'
                                    : undefined
                            }
                        >
                            <DialogTitle className="pr-6">{title}</DialogTitle>
                            {description ? (
                                <DialogDescription>
                                    {description}
                                </DialogDescription>
                            ) : null}
                            {headerEnd}
                        </DialogHeader>
                        {children ? (
                            <DialogBody className={bodyClassName}>
                                <UnsavedChangesScope.Provider value={scope}>
                                    {children}
                                </UnsavedChangesScope.Provider>
                            </DialogBody>
                        ) : null}
                        <DialogFooter>
                            {footerStart ? (
                                <div className="mr-auto flex items-center gap-2">
                                    {footerStart}
                                </div>
                            ) : null}
                            {!hideCancel ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={requestClose}
                                    disabled={submitting}
                                >
                                    {cancelLabel ??
                                        t('common.cancel', 'Cancel')}
                                </Button>
                            ) : null}
                            {!hideSubmit ? (
                                <Button
                                    type="submit"
                                    variant={submitVariant}
                                    disabled={!canSubmit}
                                    loading={submitting}
                                >
                                    {submitIcon}
                                    {submitLabel ?? t('common.save', 'Save')}
                                </Button>
                            ) : null}
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmDiscard} onOpenChange={setConfirmDiscard}>
                <DialogContent size="sm">
                    <DialogHeader>
                        <DialogTitle>
                            {t('dialog.discard.title', 'Discard changes?')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'dialog.discard.description',
                                'The changes you made in this dialog will be lost.'
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmDiscard(false)}
                        >
                            {t('dialog.discard.keep', 'Keep editing')}
                        </Button>
                        <Button variant="destructive" onClick={close}>
                            {t('dialog.discard.confirm', 'Discard')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
