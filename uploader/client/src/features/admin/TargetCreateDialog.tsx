'use client';

import {useState} from 'react';
import {useQueryClient} from '@tanstack/react-query';
import {useTranslation} from 'react-i18next';
import {FieldError, FormRow, Input, Textarea} from '@alchemy/next-ui/ui/input';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {isApiError} from '@alchemy/next-ui/utils/errors';
import {createTarget} from '@/lib/api/uploader';
import {targetsQueryKey} from '@/features/targets/TargetListScreen';
import type {Target, TargetInput} from '@/types/api';
import {violationsByField} from './violations';

type Values = Record<keyof TargetInput, string>;

const EMPTY: Values = {
    name: '',
    slug: '',
    description: '',
    targetUrl: '',
    authorizationScheme: '',
    authorizationKey: '',
    defaultDestination: '',
};

/** The API tells an unset setting by null, never by an empty string */
function toInput(values: Values): TargetInput {
    const orNull = (v: string) => v.trim() || null;

    return {
        name: values.name.trim(),
        slug: orNull(values.slug),
        description: orNull(values.description),
        targetUrl: orNull(values.targetUrl),
        authorizationScheme: orNull(values.authorizationScheme),
        authorizationKey: orNull(values.authorizationKey),
        defaultDestination: orNull(values.defaultDestination),
    };
}

/**
 * Creates a target, open to everyone. Who may upload to it is set in the
 * admin of the API.
 */
export function TargetCreateDialog({
    open,
    onOpenChange,
    resolve,
}: ModalProps<Target>) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const [values, setValues] = useState<Values>(EMPTY);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const set = (patch: Partial<Values>) => setValues(v => ({...v, ...patch}));
    const push = !!values.targetUrl.trim();

    const submit = async () => {
        setErrors({});
        try {
            const created = await createTarget(toInput(values));
            await queryClient.invalidateQueries({queryKey: targetsQueryKey});
            resolve?.(created);

            return false;
        } catch (e) {
            if (isApiError(e, 422)) {
                setErrors(violationsByField(e));

                return false;
            }
            throw e;
        }
    };

    const field = (
        key: keyof Values,
        label: string,
        help?: string,
        props: React.ComponentProps<typeof Input> = {}
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
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="md"
            title={t('admin.target_create.title', 'New target')}
            submitLabel={t('admin.target_create.submit', 'Create the target')}
            canSubmit={!!values.name.trim()}
            dirty={values !== EMPTY}
            onSubmit={submit}
        >
            <div data-testid="target-create-form">
                {field(
                    'name',
                    t('admin.target_create.name', 'Name'),
                    undefined,
                    {required: true, autoFocus: true}
                )}
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
                            {type: 'password', autoComplete: 'off'}
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
                {errors[''] ? <FieldError>{errors['']}</FieldError> : null}
            </div>
        </FormDialog>
    );
}
