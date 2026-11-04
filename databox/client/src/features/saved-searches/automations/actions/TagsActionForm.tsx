'use client';

import {useTranslation} from 'react-i18next';
import {TagsIcon} from 'lucide-react';
import {TagSelect} from '@/components/form/selects';
import {FormRow} from '@/components/ui/input';
import type {ActionDefinition, ActionFormProps} from './types';

export function TagsActionForm({value, onChange, errors}: ActionFormProps) {
    const {t} = useTranslation();
    const add: string[] = value.add ?? [];
    const remove: string[] = value.remove ?? [];

    return (
        <div>
            <FormRow
                label={t('automation.action.tags.add', 'Tags to add')}
                error={errors?.add}
            >
                <TagSelect
                    multiple
                    value={add}
                    onChange={v => onChange({...value, add: v})}
                />
            </FormRow>
            <FormRow
                label={t('automation.action.tags.remove', 'Tags to remove')}
                error={errors?.remove}
                help={t(
                    'automation.action.tags.help',
                    'A tag only applies to the assets of its workspace.'
                )}
            >
                <TagSelect
                    multiple
                    value={remove}
                    onChange={v => onChange({...value, remove: v})}
                />
            </FormRow>
        </div>
    );
}

export const tagsAction: ActionDefinition = {
    label: t => t('automation.action.tags.label', 'Add or remove tags'),
    icon: <TagsIcon />,
    Form: TagsActionForm,
    summary: (config, t) => {
        const parts: string[] = [];
        if (config.add?.length) {
            parts.push(
                t('automation.action.tags.summary_add', 'add {{count}} tags', {
                    count: config.add.length,
                })
            );
        }
        if (config.remove?.length) {
            parts.push(
                t(
                    'automation.action.tags.summary_remove',
                    'remove {{count}} tags',
                    {
                        count: config.remove.length,
                    }
                )
            );
        }

        return parts.length
            ? t('automation.action.tags.summary', 'Tags: {{changes}}', {
                  changes: parts.join(', '),
              })
            : t('automation.action.tags.label', 'Add or remove tags');
    },
    defaultConfig: () => ({add: [], remove: []}),
    isComplete: config => !!(config.add?.length || config.remove?.length),
};
