'use client';

import {useTranslation} from 'react-i18next';
import {FolderInputIcon} from 'lucide-react';
import {FieldHelp} from '@/components/ui/input';
import {CollectionPickerField} from './CollectionPickerField';
import type {ActionDefinition, ActionFormProps} from './types';

export function MoveToCollectionForm({
    value,
    onChange,
    errors,
}: ActionFormProps) {
    const {t} = useTranslation();

    return (
        <div>
            <CollectionPickerField
                value={value.collectionId}
                onChange={collectionId => onChange({...value, collectionId})}
                error={errors?.collectionId}
            />
            <FieldHelp>
                {t(
                    'automation.action.move_to_collection.help',
                    'Assets are moved within their workspace: assets of another workspace are skipped.'
                )}
            </FieldHelp>
        </div>
    );
}

export const moveToCollectionAction: ActionDefinition = {
    label: t =>
        t('automation.action.move_to_collection.label', 'Move to a collection'),
    icon: <FolderInputIcon />,
    Form: MoveToCollectionForm,
    summary: (_config, t) =>
        t(
            'automation.action.move_to_collection.summary',
            'Move the assets to a collection'
        ),
    defaultConfig: () => ({}),
    isComplete: config => !!config.collectionId,
};
