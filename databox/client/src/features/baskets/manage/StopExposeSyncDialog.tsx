'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {Checkbox, LabeledControl} from '@alchemy/next-ui/ui/controls';

/**
 * Confirms the end of a basket synchronization, and whether the Expose
 * publication goes with it (kept by default: it may be shared already).
 */
export function StopExposeSyncDialog({
    open,
    onOpenChange,
    name,
    onConfirm,
}: ModalProps & {
    name: string;
    onConfirm: (deletePublication: boolean) => Promise<unknown>;
}) {
    const {t} = useTranslation();
    const [deletePublication, setDeletePublication] = useState(false);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t(
                'integrations.expose.stop_title',
                'Stop the synchronization of "{{name}}"?',
                {name}
            )}
            description={t(
                'integrations.expose.stop_help',
                'The changes of the basket will no longer be sent to Expose.'
            )}
            submitLabel={t('integrations.expose.stop', 'Stop')}
            submitVariant="destructive"
            onSubmit={() => onConfirm(deletePublication)}
        >
            <LabeledControl
                label={t(
                    'integrations.expose.delete_publication',
                    'Also delete the publication from Expose'
                )}
                description={t(
                    'integrations.expose.delete_publication_help',
                    'Otherwise the publication and its assets remain on Expose.'
                )}
            >
                <Checkbox
                    checked={deletePublication}
                    onCheckedChange={v => setDeletePublication(v === true)}
                    data-testid="expose-delete-publication"
                />
            </LabeledControl>
        </FormDialog>
    );
}
