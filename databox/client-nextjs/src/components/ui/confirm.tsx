'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from './dialog';
import {Button} from './button';
import {Input} from './input';
import type {ModalProps} from '@/components/modals/ModalProvider';

export type ConfirmDialogProps = ModalProps & {
    title: ReactNode;
    description?: ReactNode;
    children?: ReactNode;
    confirmLabel?: ReactNode;
    cancelLabel?: ReactNode;
    destructive?: boolean;
    /** When set, the user must type this text to enable the confirm button */
    textToType?: string;
    onConfirm: () => Promise<unknown> | unknown;
    onConfirmed?: () => void;
    disabled?: boolean;
};

export function ConfirmDialog({
    open,
    onOpenChange,
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
    const [loading, setLoading] = useState(false);

    const canConfirm =
        !disabled &&
        !loading &&
        (!textToType || typed.trim() === textToType.trim());

    const confirm = async () => {
        setLoading(true);
        try {
            await onConfirm();
            onOpenChange(false);
            onConfirmed?.();
        } catch (e: any) {
            toast.error(e?.message ?? t('common.error', 'An error occurred'));
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="sm">
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description ? (
                        <DialogDescription>{description}</DialogDescription>
                    ) : null}
                </DialogHeader>
                {children || textToType ? (
                    <DialogBody className="space-y-3">
                        {children}
                        {textToType ? (
                            <div>
                                <p className="mb-2 text-sm text-muted-foreground">
                                    {t(
                                        'confirm.type_to_confirm',
                                        'Type "{{text}}" to confirm:',
                                        {
                                            text: textToType,
                                        }
                                    )}
                                </p>
                                <Input
                                    autoFocus
                                    value={typed}
                                    onChange={e => setTyped(e.target.value)}
                                    onKeyDown={e => {
                                        if (e.key === 'Enter' && canConfirm) {
                                            void confirm();
                                        }
                                    }}
                                />
                            </div>
                        ) : null}
                    </DialogBody>
                ) : null}
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={loading}
                    >
                        {cancelLabel ?? t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={confirm}
                        disabled={!canConfirm}
                        loading={loading}
                    >
                        {confirmLabel ?? t('common.confirm', 'Confirm')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
