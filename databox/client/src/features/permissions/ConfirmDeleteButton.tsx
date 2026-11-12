'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Trash2Icon} from 'lucide-react';
import {Button} from '@alchemy/next-ui/ui/button';
import {Spinner} from '@alchemy/next-ui/ui/loader';
import {cn} from '@alchemy/next-ui/utils/cn';

/**
 * Trash icon turning into a "Delete?" button on the first click, deleting on
 * the second one. Leaving the button cancels.
 */
export function ConfirmDeleteButton({
    onConfirm,
    disabled,
}: {
    onConfirm: () => Promise<unknown>;
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const [state, setState] = useState<'idle' | 'confirm' | 'deleting'>('idle');

    if (state === 'deleting') {
        return (
            <span className="inline-flex size-7 items-center justify-center">
                <Spinner className="size-4" />
            </span>
        );
    }

    const confirming = state === 'confirm';

    return (
        <Button
            variant={confirming ? 'destructive' : 'ghost'}
            size={confirming ? 'sm' : 'icon-xs'}
            className={cn(confirming ? 'h-7 px-2' : 'text-destructive')}
            disabled={disabled}
            aria-label={confirming ? undefined : t('common.delete', 'Delete')}
            onClick={async e => {
                if (!confirming) {
                    // Safari does not focus a clicked button, and its blur cancels
                    e.currentTarget.focus();
                    setState('confirm');

                    return;
                }
                setState('deleting');
                try {
                    await onConfirm();
                } finally {
                    setState('idle');
                }
            }}
            onBlur={() => setState(s => (s === 'confirm' ? 'idle' : s))}
        >
            {confirming ? t('acl.delete_confirm', 'Delete?') : <Trash2Icon />}
        </Button>
    );
}
