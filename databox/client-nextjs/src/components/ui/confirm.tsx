'use client';

import {ReactNode, useCallback, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Input} from './input';
import {FormDialog} from '@/components/modals/FormDialog';
import {useModals, type ModalProps} from '@/components/modals/ModalProvider';

export type ConfirmOptions = {
    title: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    confirmLabel?: ReactNode;
    cancelLabel?: ReactNode;
    destructive?: boolean;
    /** When set, the user must type this text to enable the confirm button */
    textToType?: string;
    /**
     * Runs while the dialog is still open, so the action keeps its spinner and
     * its errors are reported in place. Omit it to only collect the answer.
     */
    onConfirm?: () => Promise<unknown> | unknown;
    onConfirmed?: () => void;
    disabled?: boolean;
};

export type ConfirmDialogProps = ModalProps<boolean> & ConfirmOptions;

export function ConfirmDialog({
    open,
    onOpenChange,
    resolve,
    title,
    description,
    children,
    confirmLabel,
    cancelLabel,
    destructive,
    textToType,
    onConfirm,
    onConfirmed,
    disabled,
}: ConfirmDialogProps) {
    const {t} = useTranslation();
    const [typed, setTyped] = useState('');

    const body =
        children || textToType ? (
            <>
                {children}
                {textToType ? (
                    <div>
                        <p className="mb-2 text-sm text-muted-foreground">
                            {t(
                                'confirm.type_to_confirm',
                                'Type "{{text}}" to confirm:',
                                {text: textToType}
                            )}
                        </p>
                        <Input
                            autoFocus
                            value={typed}
                            onChange={e => setTyped(e.target.value)}
                        />
                    </div>
                ) : null}
            </>
        ) : undefined;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            submitLabel={confirmLabel ?? t('common.confirm', 'Confirm')}
            cancelLabel={cancelLabel}
            submitVariant={destructive ? 'destructive' : 'default'}
            canSubmit={
                !disabled && (!textToType || typed.trim() === textToType.trim())
            }
            bodyClassName="space-y-3"
            onSubmit={async () => {
                await onConfirm?.();
                onConfirmed?.();
                resolve?.(true);
            }}
        >
            {body}
        </FormDialog>
    );
}

/**
 * Promise-based confirmation: `if (await confirm({...})) { … }`.
 *
 * Prefer passing `onConfirm` when the action is asynchronous, so the dialog
 * stays up with a spinner until it completes.
 */
export function useConfirm(): (options: ConfirmOptions) => Promise<boolean> {
    const {openModal} = useModals();

    return useCallback(
        async options => (await openModal(ConfirmDialog, options)) === true,
        [openModal]
    );
}
