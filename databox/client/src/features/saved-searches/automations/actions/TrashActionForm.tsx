'use client';

import {useTranslation} from 'react-i18next';
import {Trash2Icon, TriangleAlertIcon} from 'lucide-react';
import {Alert} from '@/components/ui/misc';
import type {ActionDefinition} from './types';

export function TrashActionForm() {
    const {t} = useTranslation();

    return (
        <Alert variant="warning" icon={<TriangleAlertIcon />}>
            {t(
                'automation.action.trash.help',
                'The assets are moved to the trash, where they can be restored. Assets the owner of the search is not allowed to delete are skipped.'
            )}
        </Alert>
    );
}

export const trashAction: ActionDefinition = {
    label: t => t('automation.action.trash.label', 'Move to the trash'),
    icon: <Trash2Icon />,
    Form: TrashActionForm,
    summary: (_config, t) =>
        t('automation.action.trash.summary', 'Move the assets to the trash'),
    defaultConfig: () => ({}),
    isComplete: () => true,
};
