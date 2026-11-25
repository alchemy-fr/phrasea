'use client';

import type {ComponentProps} from 'react';
import {useTranslation} from 'react-i18next';
import {FormRow, Input, Textarea} from '@alchemy/next-ui/ui/input';
import {GroupSelect} from '@alchemy/next-ui/groups/GroupSelect';
import {groupsApi} from '@/lib/api/groups';
import type {TargetInput} from '@/types/api';

type TextKey = Exclude<keyof TargetInput, 'allowedGroups'>;

/** The properties of a target as edited: an empty string is an unset one */
export type TargetValues = Record<TextKey, string> & {
    allowedGroups: string[];
};

export const EMPTY_TARGET_VALUES: TargetValues = {
    name: '',
    slug: '',
    description: '',
    targetUrl: '',
    authorizationScheme: '',
    authorizationKey: '',
    defaultDestination: '',
    allowedGroups: [],
};

/**
 * The API tells an unset property by null, never by an empty string. The
 * authorization goes with the target URL (the push mode), and goes without it.
 */
export function toTargetInput(values: TargetValues): TargetInput {
    const orNull = (v: string) => v.trim() || null;
    const targetUrl = orNull(values.targetUrl);

    return {
        name: values.name.trim(),
        slug: orNull(values.slug),
        description: orNull(values.description),
        targetUrl,
        authorizationScheme: targetUrl
            ? orNull(values.authorizationScheme)
            : null,
        authorizationKey: targetUrl ? orNull(values.authorizationKey) : null,
        defaultDestination: orNull(values.defaultDestination),
        allowedGroups:
            values.allowedGroups.length > 0 ? values.allowedGroups : null,
    };
}

/**
 * The properties of a target, shared by its creation and its editor. The
 * authorization only applies to the push mode: its fields show once a target
 * URL is set.
 */
export function TargetPropertiesFields({
    values,
    errors,
    set,
    authorizationKeyPlaceholder,
    autoFocus,
}: {
    values: TargetValues;
    /** The violations by property, as `violationsByField` gives them */
    errors: Record<string, string>;
    set: (patch: Partial<TargetValues>) => void;
    /** Tells that a key is set, which is never read back */
    authorizationKeyPlaceholder?: string;
    /** Focuses the name */
    autoFocus?: boolean;
}) {
    const {t} = useTranslation();
    const push = !!values.targetUrl.trim();
    // The violations of an item come at `allowedGroups[i]`
    const groupsError =
        errors.allowedGroups ??
        Object.entries(errors).find(([path]) =>
            path.startsWith('allowedGroups[')
        )?.[1];

    const field = (
        key: TextKey,
        label: string,
        help?: string,
        props: ComponentProps<typeof Input> = {}
    ) => (
        <FormRow
            label={label}
            htmlFor={`target-${key}`}
            help={help}
            error={errors[key]}
        >
            <Input
                id={`target-${key}`}
                data-testid={`target-${key}-input`}
                value={values[key]}
                onChange={e => set({[key]: e.target.value})}
                {...props}
            />
        </FormRow>
    );

    return (
        <>
            {field('name', t('admin.target_create.name', 'Name'), undefined, {
                required: true,
                autoFocus,
            })}
            {field(
                'slug',
                t('admin.target_create.slug', 'Slug'),
                t(
                    'admin.target_create.slug_help',
                    'Optional, unique: lowercase letters, digits, "-" and "_", starting with a letter.'
                ),
                {pattern: '[a-z][a-z0-9_\\-]+'}
            )}
            <FormRow
                label={t('admin.target_create.description', 'Description')}
                htmlFor="target-description"
                error={errors.description}
            >
                <Textarea
                    id="target-description"
                    data-testid="target-description-input"
                    rows={3}
                    value={values.description}
                    onChange={e => set({description: e.target.value})}
                />
            </FormRow>
            {field(
                'targetUrl',
                t('admin.target_create.target_url', 'Target URL'),
                t(
                    'admin.target_create.target_url_help',
                    'Where the uploads are pushed, in https. Leave empty for the pull mode: the destination fetches them.'
                ),
                {type: 'url', placeholder: 'https://'}
            )}
            {push ? (
                <>
                    {field(
                        'authorizationScheme',
                        t(
                            'admin.target_create.authorization_scheme',
                            'Authorization scheme'
                        ),
                        t(
                            'admin.target_create.authorization_scheme_help',
                            '"OAuth" for Phraseanet. Defaults to "ApiKey".'
                        )
                    )}
                    {field(
                        'authorizationKey',
                        t(
                            'admin.target_create.authorization_key',
                            'Authorization key'
                        ),
                        undefined,
                        {
                            type: 'password',
                            autoComplete: 'off',
                            placeholder: authorizationKeyPlaceholder,
                        }
                    )}
                </>
            ) : null}
            {field(
                'defaultDestination',
                t(
                    'admin.target_create.default_destination',
                    'Default destination'
                ),
                t(
                    'admin.target_create.default_destination_help',
                    'The ID of a Databox collection, or of a Phraseanet collection.'
                )
            )}
            <FormRow
                label={t(
                    'admin.target_create.allowed_groups',
                    'Allowed groups'
                )}
                htmlFor="target-allowedGroups"
                help={t(
                    'admin.target_create.allowed_groups_help',
                    'Leave empty to allow every user.'
                )}
                error={groupsError}
            >
                <div data-testid="target-allowedGroups-input">
                    <GroupSelect
                        id="target-allowedGroups"
                        api={groupsApi}
                        value={values.allowedGroups}
                        onChange={allowedGroups => set({allowedGroups})}
                    />
                </div>
            </FormRow>
        </>
    );
}
