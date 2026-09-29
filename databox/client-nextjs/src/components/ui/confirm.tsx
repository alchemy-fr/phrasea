'use client';

import {ReactNode, useCallback, useState} from 'react';
import {Trans, useTranslation} from 'react-i18next';
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
                    <TypeToConfirm
                        text={textToType}
                        value={typed}
                        onChange={setTyped}
                    />
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
 * Field asking to type a word before a dangerous action. The word is in a
 * `<code>` selected as a whole on click, to be copied at once.
 */
export function TypeToConfirm({
    text,
    value,
    onChange,
}: {
    text: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <p className="mb-2 text-sm text-muted-foreground">
                <Trans
                    i18nKey="confirm.type_to_confirm"
                    defaults="Type <code>{{text}}</code> to confirm:"
                    values={{text}}
                    components={{
                        code: (
                            <code className="rounded bg-muted px-1.5 py-0.5 font-mono text-foreground select-all" />
                        ),
                    }}
                />
            </p>
            <Input
                autoFocus
                autoComplete="off"
                value={value}
                onChange={e => onChange(e.target.value)}
            />
        </div>
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
