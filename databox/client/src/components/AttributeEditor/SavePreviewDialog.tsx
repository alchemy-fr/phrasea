import {AppDialog} from '@alchemy/phrasea-ui';
import type {StackedModalProps} from '@alchemy/navigation';
import {useModals} from '@alchemy/navigation';
import {useTranslation} from 'react-i18next';
import ValueDiff, {ValueDiffProps} from './ValueDiff.tsx';
import {workspaceAttributeBatchUpdate} from '../../api/asset.ts';
import React from 'react';
import {Button} from '@mui/material';
import {LoadingButton} from '@mui/lab';

type Props = {
    workspaceId: string;
    onSaved: () => void;
} & ValueDiffProps &
    StackedModalProps;

export default function SavePreviewDialog({
    open,
    modalIndex,
    workspaceId,
    actions,
    onSaved,
    definitionIndex,
    ...props
}: Props) {
    const {t} = useTranslation();
    const {closeModal} = useModals();
    const [saving, setSaving] = React.useState(false);

    const doSave = async () => {
        if (actions.length > 0) {
            setSaving(true);
            try {
                await workspaceAttributeBatchUpdate(workspaceId, actions.map(a => ({
                    ...a,
                    value: definitionIndex[a.definitionId!].entity ? a.value?.id : a.value,
                })));
                closeModal();
                onSaved();
            } finally {
                setSaving(false);
            }
        }
    };

    return (
        <AppDialog
            onClose={closeModal}
            open={open}
            modalIndex={modalIndex}
            title={t('attribute_editor.diff.dialog.title', 'Confirm Changes?')}
            actions={({onClose}) => (
                <>
                    <Button onClick={onClose}>
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <LoadingButton
                        loading={saving}
                        disabled={saving}
                        variant={'contained'}
                        onClick={doSave}
                        color={'primary'}
                    >
                        {t('common.save', 'Save')}
                    </LoadingButton>
                </>
            )}
        >
            <ValueDiff actions={actions} definitionIndex={definitionIndex} {...props} />
        </AppDialog>
    );
}
