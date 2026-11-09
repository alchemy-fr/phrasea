'use client';

import {useId} from 'react';
import {useTranslation} from 'react-i18next';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {LocaleMode} from '@/types/api';
import {TargetSelect} from './TargetSelect';

export type FormSchemaMeta = {
    target: string | undefined;
    locale: string;
    localeMode: LocaleMode;
};

/** What every form schema has, whatever its format */
export function FormSchemaMetaFields({
    value,
    onChange,
    errors,
    disabled,
}: {
    value: FormSchemaMeta;
    onChange: (value: FormSchemaMeta) => void;
    errors: Record<string, string>;
    disabled?: boolean;
}) {
    const {t} = useTranslation();
    const targetId = useId();
    const localeId = useId();
    const localeModeId = useId();

    const localeModes = [
        {
            value: String(LocaleMode.NoLocale),
            label: t('admin.locale_mode.none', 'No locale'),
        },
        {
            value: String(LocaleMode.UserAgent),
            label: t('admin.locale_mode.user_agent', "User's language"),
        },
        {
            value: String(LocaleMode.Forced),
            label: t('admin.locale_mode.forced', 'Forced to this locale'),
        },
    ];

    return (
        <>
            <FormRow
                label={t('admin.form_schema.target', 'Target')}
                htmlFor={targetId}
                error={errors.target}
            >
                <TargetSelect
                    id={targetId}
                    value={value.target}
                    onChange={target => onChange({...value, target})}
                    disabled={disabled}
                />
            </FormRow>
            <div className="grid gap-x-4 sm:grid-cols-2">
                <FormRow
                    label={t('admin.form_schema.locale', 'Locale')}
                    htmlFor={localeId}
                    error={errors.locale}
                    help={t(
                        'admin.form_schema.locale_help',
                        'e.g. "en", "fr"; empty for every language.'
                    )}
                >
                    <Input
                        id={localeId}
                        name="locale"
                        maxLength={5}
                        value={value.locale}
                        disabled={disabled}
                        onChange={e =>
                            onChange({...value, locale: e.target.value})
                        }
                    />
                </FormRow>
                <FormRow
                    label={t(
                        'admin.form_schema.locale_mode',
                        'Locale of the commit'
                    )}
                    htmlFor={localeModeId}
                    error={errors.localeMode}
                >
                    <SimpleSelect<string>
                        id={localeModeId}
                        value={String(value.localeMode)}
                        options={localeModes}
                        disabled={disabled}
                        onValueChange={v =>
                            onChange({
                                ...value,
                                localeMode: Number(v) as LocaleMode,
                            })
                        }
                    />
                </FormRow>
            </div>
        </>
    );
}
