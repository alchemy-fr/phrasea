'use client';

import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {GlobeIcon, KeyRoundIcon} from 'lucide-react';
import {AutomationScope, type WorkspaceIntegration} from '@/types/api';
import {
    getIntegrationsOfContext,
    IntegrationContext,
} from '@/lib/api/integrations';
import {
    createExposePublication,
    getExposeProfiles,
    getExposePublications,
} from '@/lib/api/expose';
import {
    AsyncCombobox,
    type ComboOption,
} from '@alchemy/next-ui/form/AsyncCombobox';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {
    type ModalProps,
    useModals,
} from '@alchemy/next-ui/modals/ModalProvider';
import {Button} from '@alchemy/next-ui/ui/button';
import {Checkbox, LabeledControl, Switch} from '@alchemy/next-ui/ui/controls';
import {FieldError, FormRow, Input, Textarea} from '@alchemy/next-ui/ui/input';
import {InlineLoader} from '@alchemy/next-ui/ui/loader';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {integrationLabel} from '@/features/integrations/integrationLabel';
import {useIntegrationAuth} from '@/features/integrations/useIntegrationAuth';
import type {ActionDefinition, ActionFormProps} from './types';

type CreatedPublication = {id: string; title: string};

function CreateExposePublicationDialog({
    open,
    onOpenChange,
    resolve,
    integrationId,
    defaultTitle,
}: ModalProps<CreatedPublication> & {
    integrationId: string;
    defaultTitle: string;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState({
        title: defaultTitle,
        slug: '',
        description: '',
        enabled: true,
        parent: '',
        profile: '',
    });
    const profiles = useQuery({
        queryKey: ['expose', integrationId, 'profiles'],
        queryFn: () => getExposeProfiles(integrationId),
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t(
                'automation.action.expose_publish.create_title',
                'New Expose publication'
            )}
            submitLabel={t('common.create', 'Create')}
            canSubmit={!!form.title.trim()}
            onSubmit={async () => {
                const created = await createExposePublication(integrationId, {
                    title: form.title.trim(),
                    slug: form.slug.trim() || undefined,
                    description: form.description.trim() || undefined,
                    enabled: form.enabled,
                    parent: form.parent || undefined,
                    profile: form.profile || undefined,
                });
                resolve?.(created);

                return false;
            }}
        >
            <FormRow label={t('integrations.expose.title', 'Title')}>
                <Input
                    autoFocus
                    value={form.title}
                    onChange={e => setForm({...form, title: e.target.value})}
                />
            </FormRow>
            <FormRow label={t('integrations.expose.slug', 'Slug')}>
                <Input
                    value={form.slug}
                    onChange={e => setForm({...form, slug: e.target.value})}
                />
            </FormRow>
            <FormRow label={t('common.description', 'Description')}>
                <Textarea
                    value={form.description}
                    onChange={e =>
                        setForm({...form, description: e.target.value})
                    }
                />
            </FormRow>
            <FormRow
                label={t(
                    'automation.action.expose_publish.profile',
                    'Publication profile'
                )}
            >
                {profiles.isLoading ? (
                    <InlineLoader />
                ) : (
                    <SimpleSelect
                        value={form.profile || undefined}
                        onValueChange={profile => setForm({...form, profile})}
                        placeholder={t(
                            'automation.action.expose_publish.no_profile',
                            'None'
                        )}
                        options={(profiles.data ?? []).map(p => ({
                            value: p.id,
                            label: p.name,
                        }))}
                    />
                )}
            </FormRow>
            <FormRow
                label={t(
                    'automation.action.expose_publish.parent',
                    'Parent publication'
                )}
            >
                <AsyncCombobox
                    queryKey={['expose', integrationId, 'publications']}
                    loadOptions={query =>
                        loadPublicationOptions(integrationId, query)
                    }
                    value={form.parent || undefined}
                    onChange={parent =>
                        setForm({...form, parent: parent ?? ''})
                    }
                    placeholder={t(
                        'automation.action.expose_publish.no_parent',
                        'None'
                    )}
                />
            </FormRow>
            <LabeledControl label={t('common.enabled', 'Enabled')}>
                <Checkbox
                    checked={form.enabled}
                    onCheckedChange={v =>
                        setForm({...form, enabled: v === true})
                    }
                />
            </LabeledControl>
        </FormDialog>
    );
}

async function loadPublicationOptions(
    integrationId: string,
    title: string
): Promise<ComboOption[]> {
    return (await getExposePublications(integrationId, {title})).map(p => ({
        value: p.id,
        label: p.title,
    }));
}

/**
 * Whether the mirror option can be used: the assets which left the results
 * are only known when every result is processed.
 */
function isMirrorAllowed(scope: AutomationScope | undefined): boolean {
    return scope === AutomationScope.All;
}

export function ExposePublishForm({
    value,
    onChange,
    errors,
    scope,
}: ActionFormProps) {
    const {t} = useTranslation();
    const {openModal} = useModals();
    const integrations = useQuery({
        queryKey: ['integrations', IntegrationContext.Automation],
        queryFn: () => getIntegrationsOfContext(IntegrationContext.Automation),
    });
    const list: WorkspaceIntegration[] = (
        integrations.data?.items ?? []
    ).filter(i => i.integration.includes('expose'));
    const integration = list.find(i => i.id === value.integrationId);
    const auth = useIntegrationAuth(integration);
    const set = (patch: Record<string, unknown>) =>
        onChange({...value, ...patch});

    const mirrorAllowed = isMirrorAllowed(scope);

    // A single integration needs no choice
    const onlyIntegrationId = list.length === 1 ? list[0].id : undefined;
    useEffect(() => {
        if (onlyIntegrationId && !value.integrationId) {
            onChange({...value, integrationId: onlyIntegrationId});
        } else if (!mirrorAllowed && value.removeMissing) {
            onChange({...value, removeMissing: false});
        }
    }, [onlyIntegrationId, mirrorAllowed, value, onChange]);

    if (integrations.isLoading) {
        return <InlineLoader />;
    }
    if (list.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    'automation.action.expose_publish.no_integration',
                    'No Expose integration is available.'
                )}
            </p>
        );
    }

    return (
        <div>
            {list.length > 1 ? (
                <FormRow
                    label={t(
                        'automation.action.expose_publish.integration',
                        'Expose integration'
                    )}
                    error={errors?.integrationId}
                >
                    <SimpleSelect
                        value={value.integrationId || undefined}
                        onValueChange={integrationId =>
                            set({
                                integrationId,
                                publicationId: '',
                                publicationTitle: null,
                            })
                        }
                        options={list.map(i => ({
                            value: i.id,
                            label: integrationLabel(i),
                        }))}
                    />
                </FormRow>
            ) : (
                <FieldError>{errors?.integrationId}</FieldError>
            )}

            {integration && !auth.loading && !auth.authorized ? (
                <div className="mb-4 space-y-2 text-sm">
                    <p className="text-muted-foreground">
                        {t(
                            'automation.action.expose_publish.authorize_help',
                            'Authorize the access to Expose to choose the publication.'
                        )}
                    </p>
                    <Button size="sm" onClick={auth.authorize}>
                        <KeyRoundIcon />{' '}
                        {t('integrations.authorize', 'Authorize')}
                    </Button>
                </div>
            ) : null}

            {integration && auth.authorized ? (
                <FormRow
                    label={t(
                        'automation.action.expose_publish.publication',
                        'Publication'
                    )}
                    error={errors?.publicationId}
                    help={t(
                        'automation.action.expose_publish.publication_help',
                        'The owner of the saved search must be an editor of the publication: the assets are published with their Expose access.'
                    )}
                >
                    <AsyncCombobox
                        queryKey={['expose', integration.id, 'publications']}
                        loadOptions={query =>
                            loadPublicationOptions(integration.id, query)
                        }
                        resolveValue={async id => ({
                            value: id,
                            label: value.publicationTitle || id,
                        })}
                        value={value.publicationId || undefined}
                        onChange={(publicationId, option) =>
                            set({
                                publicationId: publicationId ?? '',
                                publicationTitle: option?.label ?? null,
                            })
                        }
                        placeholder={t(
                            'automation.action.expose_publish.choose_publication',
                            'Choose a publication'
                        )}
                        searchPlaceholder={t(
                            'automation.action.expose_publish.search_publication',
                            'Search by title…'
                        )}
                        createWithoutQuery
                        createLabel={query =>
                            query
                                ? t(
                                      'automation.action.expose_publish.create_named',
                                      {
                                          defaultValue:
                                              'Create the publication « {{title}} »',
                                          title: query,
                                      }
                                  )
                                : t(
                                      'automation.action.expose_publish.create',
                                      'Create a publication'
                                  )
                        }
                        onCreate={async query => {
                            const created = await openModal(
                                CreateExposePublicationDialog,
                                {
                                    integrationId: integration.id,
                                    defaultTitle: query,
                                }
                            );

                            return created
                                ? {value: created.id, label: created.title}
                                : undefined;
                        }}
                    />
                </FormRow>
            ) : null}

            <LabeledControl
                className="mb-3"
                label={t(
                    'automation.action.expose_publish.ignore_roi',
                    'Publish the whole files'
                )}
                description={t(
                    'automation.action.expose_publish.ignore_roi_help',
                    'Otherwise the files are cut to the region of interest of the assets.'
                )}
            >
                <Switch
                    checked={!!value.ignoreRoi}
                    onCheckedChange={ignoreRoi => set({ignoreRoi})}
                />
            </LabeledControl>

            <LabeledControl
                label={t(
                    'automation.action.expose_publish.remove_missing',
                    'Mirror the results'
                )}
                description={
                    mirrorAllowed
                        ? t(
                              'automation.action.expose_publish.remove_missing_help',
                              'The assets published by this automation which are no longer in the results are removed from the publication, including those the owner can no longer read. A search without results empties the publication of the assets of this automation.'
                          )
                        : t(
                              'automation.action.expose_publish.remove_missing_scope',
                              'Only with the scope "All results": the assets which left the results are known when every result is processed.'
                          )
                }
            >
                <Switch
                    checked={!!value.removeMissing && mirrorAllowed}
                    disabled={!mirrorAllowed}
                    onCheckedChange={removeMissing => set({removeMissing})}
                />
            </LabeledControl>
            <FieldError>{errors?.removeMissing}</FieldError>
        </div>
    );
}

export const exposePublishAction: ActionDefinition = {
    label: t =>
        t('automation.action.expose_publish.label', 'Publish to Expose'),
    icon: <GlobeIcon />,
    Form: ExposePublishForm,
    summary: (config, t) => {
        const publication = config.publicationTitle || config.publicationId;
        if (!publication) {
            return t(
                'automation.action.expose_publish.label',
                'Publish to Expose'
            );
        }
        const summary = t(
            'automation.action.expose_publish.summary',
            'Publish to {{publication}}',
            {publication}
        );

        return config.removeMissing
            ? `${summary} ${t('automation.action.expose_publish.mirror', '(mirror)')}`
            : summary;
    },
    defaultConfig: () => ({
        integrationId: '',
        publicationId: '',
        ignoreRoi: false,
        removeMissing: false,
    }),
    isComplete: config => !!config.integrationId && !!config.publicationId,
};
