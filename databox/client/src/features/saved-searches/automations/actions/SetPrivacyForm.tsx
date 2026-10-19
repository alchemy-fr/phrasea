'use client';

import {useTranslation} from 'react-i18next';
import {LockIcon} from 'lucide-react';
import {Privacy} from '@/types/api';
import {SimpleSelect} from '@/components/ui/select';
import {FormRow} from '@/components/ui/input';
import {privacyLabels} from '@/features/attributes/types/registry';
import type {ActionDefinition, ActionFormProps} from './types';

const privacies = Object.values(Privacy).filter(
    (p): p is Privacy => typeof p === 'number'
);

function isPrivacy(value: unknown): value is Privacy {
    return typeof value === 'number' && privacies.includes(value);
}

export function SetPrivacyForm({value, onChange, errors}: ActionFormProps) {
    const {t} = useTranslation();
    const labels = privacyLabels(t);

    return (
        <FormRow
            label={t('automation.action.set_privacy.privacy', 'New privacy')}
            error={errors?.privacy}
            help={t(
                'automation.action.set_privacy.help',
                'Only the assets whose permissions the owner of the search may edit are changed, the others are skipped.'
            )}
        >
            <SimpleSelect
                value={
                    isPrivacy(value.privacy) ? String(value.privacy) : undefined
                }
                onValueChange={v => onChange({...value, privacy: Number(v)})}
                options={privacies.map(p => ({
                    value: String(p),
                    label: labels[p],
                }))}
                placeholder={t(
                    'automation.action.set_privacy.placeholder',
                    'Choose a privacy…'
                )}
            />
        </FormRow>
    );
}

export const setPrivacyAction: ActionDefinition = {
    label: t => t('automation.action.set_privacy.label', 'Change the privacy'),
    icon: <LockIcon />,
    Form: SetPrivacyForm,
    summary: (config, t) =>
        isPrivacy(config.privacy)
            ? t(
                  'automation.action.set_privacy.summary',
                  'Privacy: {{privacy}}',
                  {
                      privacy: privacyLabels(t)[config.privacy],
                  }
              )
            : t('automation.action.set_privacy.label', 'Change the privacy'),
    defaultConfig: () => ({}),
    isComplete: config => isPrivacy(config.privacy),
};
