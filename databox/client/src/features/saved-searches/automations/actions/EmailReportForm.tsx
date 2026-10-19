'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {MailIcon} from 'lucide-react';
import {GroupSelect, UserSelect} from '@/components/form/selects';
import {FieldError, FormRow, Input, Textarea} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import type {AutomationActionConfig} from '@/types/api';
import type {ActionDefinition, ActionFormProps} from './types';

/** Same bounds as EmailReportAction in the API */
export const DEFAULT_MAX_LISTED = 50;
export const MAX_LISTED = 200;

/**
 * Email addresses typed one per line, or separated by commas, semicolons or spaces.
 */
export function parseEmails(text: string): string[] {
    return text
        .split(/[\s,;]+/)
        .map(e => e.trim())
        .filter(Boolean);
}

function countRecipients(config: AutomationActionConfig): number {
    return (
        (config.users?.length ?? 0) +
        (config.groups?.length ?? 0) +
        (config.emails?.length ?? 0)
    );
}

export function EmailReportForm({value, onChange, errors}: ActionFormProps) {
    const {t} = useTranslation();
    const [emailsText, setEmailsText] = useState<string>(() =>
        (value.emails ?? []).join('\n')
    );

    const set = (patch: Record<string, unknown>) =>
        onChange({...value, ...patch});

    return (
        <div>
            <FieldError>{errors?.recipients}</FieldError>
            <FormRow
                label={t('automation.action.email_report.users', 'Users')}
                error={errors?.users}
            >
                <UserSelect
                    multiple
                    value={value.users ?? []}
                    onChange={(users: string[]) => set({users})}
                />
            </FormRow>
            <FormRow
                label={t('automation.action.email_report.groups', 'Groups')}
                error={errors?.groups}
                help={t(
                    'automation.action.email_report.groups_help',
                    'Every member of the group receives the report.'
                )}
            >
                <GroupSelect
                    multiple
                    value={value.groups ?? []}
                    onChange={(groups: string[]) => set({groups})}
                />
            </FormRow>
            <FormRow
                label={t(
                    'automation.action.email_report.emails',
                    'Email addresses'
                )}
                error={errors?.emails}
                help={t(
                    'automation.action.email_report.emails_help',
                    'One address per line, for people without an account.'
                )}
            >
                <Textarea
                    rows={3}
                    value={emailsText}
                    placeholder="john@example.com"
                    onChange={e => {
                        setEmailsText(e.target.value);
                        set({emails: parseEmails(e.target.value)});
                    }}
                />
            </FormRow>
            <FormRow
                label={t('automation.action.email_report.subject', 'Subject')}
                error={errors?.subject}
                help={t(
                    'automation.action.email_report.subject_help',
                    'Leave empty to use the default subject.'
                )}
            >
                <Input
                    value={value.subject ?? ''}
                    maxLength={255}
                    onChange={e => set({subject: e.target.value || null})}
                />
            </FormRow>
            <FormRow
                label={t(
                    'automation.action.email_report.max_listed',
                    'Assets listed in the report'
                )}
                error={errors?.maxListed}
                help={t(
                    'automation.action.email_report.max_listed_help',
                    'The other assets are only counted (at most {{max}}).',
                    {max: MAX_LISTED}
                )}
            >
                <Input
                    type="number"
                    min={1}
                    max={MAX_LISTED}
                    value={value.maxListed ?? DEFAULT_MAX_LISTED}
                    onChange={e =>
                        set({
                            maxListed:
                                e.target.value === ''
                                    ? DEFAULT_MAX_LISTED
                                    : Number(e.target.value),
                        })
                    }
                />
            </FormRow>
            <LabeledControl
                label={t(
                    'automation.action.email_report.send_when_empty',
                    'Send the report even when there is no asset'
                )}
            >
                <Switch
                    checked={!!value.sendWhenEmpty}
                    onCheckedChange={sendWhenEmpty => set({sendWhenEmpty})}
                />
            </LabeledControl>
            <FieldError>{errors?.sendWhenEmpty}</FieldError>
        </div>
    );
}

export const emailReportAction: ActionDefinition = {
    label: t =>
        t('automation.action.email_report.label', 'Send an email report'),
    icon: <MailIcon />,
    Form: EmailReportForm,
    summary: (config, t) =>
        t(
            'automation.action.email_report.summary',
            'Email report to {{count}} recipients',
            {count: countRecipients(config)}
        ),
    defaultConfig: () => ({
        users: [],
        groups: [],
        emails: [],
        sendWhenEmpty: false,
        maxListed: DEFAULT_MAX_LISTED,
        subject: null,
    }),
    isComplete: config => countRecipients(config) > 0,
};
