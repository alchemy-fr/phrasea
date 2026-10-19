'use client';

import {useTranslation} from 'react-i18next';
import {FolderPlusIcon} from 'lucide-react';
import {FieldHelp} from '@/components/ui/input';
import {CollectionPickerField} from './CollectionPickerField';
import type {ActionDefinition, ActionFormProps} from './types';

export function AddToCollectionForm({
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
                    'automation.action.add_to_collection.help',
                    'Assets stay where they are and are also added to this collection.'
                )}
            </FieldHelp>
        </div>
    );
}

export const addToCollectionAction: ActionDefinition = {
    label: t =>
        t('automation.action.add_to_collection.label', 'Add to a collection'),
    icon: <FolderPlusIcon />,
    Form: AddToCollectionForm,
    summary: (_config, t) =>
        t(
            'automation.action.add_to_collection.summary',
            'Add the assets to a collection'
        ),
    defaultConfig: () => ({}),
    isComplete: config => !!config.collectionId,
};
