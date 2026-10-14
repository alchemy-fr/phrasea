'use client';

import {useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {KeyIcon, RepeatIcon, SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {IntegrationType, WorkspaceIntegration} from '@/types/api';
import {EntityName} from '@/types/api';
import {DefinitionManager} from '@/features/workspaces/manage/DefinitionManager';
import {
    deleteIntegration,
    getGlobalIntegrations,
    getIntegrationType,
    getIntegrationTypes,
    getWorkspaceIntegrations,
    postIntegration,
    putIntegration,
} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {CodeEditor} from '@/components/form/code/CodeEditor';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {Badge} from '@/components/ui/misc';
import {CopyButton} from '@/components/ui/copy-button';
import {AsyncCombobox, ComboOption} from '@/components/form/AsyncCombobox';
import {AclEditor} from '@/features/permissions/AclEditor';
import {integrationPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {iri} from '@/lib/utils/iri';
import {cn} from '@/lib/utils/cn';
import {ReferenceDetails} from '@/components/form/ReferenceDetails';
import {IntegrationCatalog} from './IntegrationCatalog';
import {IntegrationTypeIcon} from './integrationTypeUi';
import {integrationLabel} from '../integrationLabel';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';
import {useFocusFirstField} from '@/hooks/useFocusFirstField';

type Props = {
    /** Omitted for the instance-wide integrations */
    workspaceId?: string;
    /** Take the whole height of the parent (a flex column) */
    fill?: boolean;
};

export function IntegrationManager({workspaceId, fill}: Props) {
    const {t, i18n} = useTranslation();
    const integrations = useQuery({
        queryKey: ['integrations', 'manage', workspaceId ?? 'global'],
        queryFn: () =>
            workspaceId
                ? getWorkspaceIntegrations(workspaceId)
                : getGlobalIntegrations(),
    });
    const types = useQuery({
        // Descriptions are translated by the API
        queryKey: ['integration-types', i18n.language],
        queryFn: getIntegrationTypes,
        staleTime: Infinity,
    });
    const typeOf = (name: string) =>
        types.data?.items.find(tp => tp.name === name);

    return (
        <DefinitionManager<WorkspaceIntegration>
            items={integrations.data?.items}
            loading={integrations.isLoading}
            fill={fill}
            onChanged={() => integrations.refetch()}
            filter={(i, q) =>
                integrationLabel(i).toLowerCase().includes(q) ||
                i.integration.toLowerCase().includes(q)
            }
            renderItem={i => {
                const type = typeOf(i.integration);

                return (
                    <span
                        className={cn(
                            'flex items-center gap-2',
                            !i.enabled && 'text-destructive'
                        )}
                    >
                        {type ? (
                            <IntegrationTypeIcon
                                type={type}
                                className="size-6 rounded-md [&>svg]:size-3.5"
                            />
                        ) : null}
                        <span className="flex-1 truncate">
                            {integrationLabel(i)}
                        </span>
                        {i.name ? (
                            <Badge variant="muted">
                                {type?.displayName ?? i.integration}
                            </Badge>
                        ) : null}
                    </span>
                );
            }}
            onDelete={i => deleteIntegration(i.id)}
            createLabel={t('integration.create', 'New integration')}
            emptyLabel={
                workspaceId
                    ? undefined
                    : t(
                          'integration.instance.empty',
                          'No instance-wide integration yet'
                      )
            }
            renderForm={(i, onSaved) => (
                <IntegrationForm
                    key={i?.id ?? 'new'}
                    integration={i}
                    workspaceId={workspaceId}
                    types={types.data?.items}
                    all={integrations.data?.items ?? []}
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function IntegrationForm({
    integration: i,
    workspaceId,
    types,
    all,
    onSaved,
}: {
    integration?: WorkspaceIntegration;
    workspaceId?: string;
    types: IntegrationType[] | undefined;
    all: WorkspaceIntegration[];
    onSaved: (i: WorkspaceIntegration) => void;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState({
        integration: i?.integration ?? '',
        name: i?.name ?? '',
        enabled: i?.enabled ?? true,
        public: i?.public ?? false,
        if: i?.if ?? '',
        needs: i?.needs ?? [],
        configYaml: i?.configYaml ?? '',
    });
    const [saving, setSaving] = useState(false);
    // The type alone (picked from the catalog) is nothing worth keeping
    const {integration: _type, ...edited} = form;
    const {markSaved} = useDirtyState(edited);
    // Type picked in the catalog: on to the form
    const formRef = useRef<HTMLDivElement>(null);
    useFocusFirstField(formRef, !i && !!form.integration);
    const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
        setForm(f => ({...f, [k]: v}));
    const reference = useQuery({
        queryKey: ['integration-type', form.integration],
        queryFn: () => getIntegrationType(form.integration),
        enabled: !!form.integration,
    });
    const type = types?.find(tp => tp.name === form.integration);
    // IF and Needs only drive the workflow jobs
    const isWorkflow = !type || type.features.includes('workflow');
    const needOptions: ComboOption[] = all
        .filter(x => x.id !== i?.id)
        .map(x => ({value: x['@id'], label: integrationLabel(x)}));

    const save = async () => {
        setSaving(true);
        try {
            const data: Partial<WorkspaceIntegration> & {name?: string} = {
                integration: form.integration,
                // The API input names the label `name`
                name: form.name,
                enabled: form.enabled,
                public: form.public,
                if: isWorkflow ? form.if || undefined : undefined,
                needs: isWorkflow ? form.needs : undefined,
                configYaml: form.configYaml,
            };
            const saved = i
                ? await putIntegration(i.id, data)
                : await postIntegration({
                      ...data,
                      workspace: workspaceId
                          ? iri(EntityName.Workspace, workspaceId)
                          : null,
                  });
            toast.success(t('integration.saved', 'Integration saved'));
            markSaved();
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    if (!i && !form.integration) {
        return (
            <IntegrationCatalog
                types={types}
                existing={all}
                instance={!workspaceId}
                onSelect={tp =>
                    setForm(f => ({
                        ...f,
                        integration: tp.name,
                    }))
                }
            />
        );
    }

    return (
        <div ref={formRef} className="space-y-4">
            <div className="flex items-start gap-3 rounded-lg border bg-muted/30 p-3">
                {type ? <IntegrationTypeIcon type={type} /> : null}
                <div className="min-w-0 flex-1">
                    <div className="font-medium">
                        {type?.displayName ?? form.integration}
                    </div>
                    {type ? (
                        <p className="text-sm text-muted-foreground">
                            {type.description}
                        </p>
                    ) : null}
                </div>
                {!i ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        data-testid="integration-change-type"
                        onClick={() => setForm(f => ({...f, integration: ''}))}
                    >
                        <RepeatIcon /> {t('integration.change_type', 'Change')}
                    </Button>
                ) : null}
            </div>
            <FormRow label={t('integration.title', 'Title')}>
                <Input
                    value={form.name}
                    onChange={e => set('name', e.target.value)}
                    placeholder={type?.displayName}
                />
            </FormRow>
            <div className="flex flex-wrap items-start gap-6">
                <LabeledControl label={t('common.enabled', 'Enabled')}>
                    <Switch
                        checked={form.enabled}
                        onCheckedChange={v => set('enabled', v)}
                    />
                </LabeledControl>
                <LabeledControl
                    label={t('common.public', 'Public')}
                    description={t(
                        'integration.public_help',
                        'Usable by everyone; otherwise grant access below.'
                    )}
                >
                    <Switch
                        checked={form.public}
                        onCheckedChange={v => set('public', v)}
                    />
                </LabeledControl>
            </div>
            {isWorkflow ? (
                <>
                    <FormRow
                        label={t('integration.if', 'Condition (IF expression)')}
                    >
                        <Input
                            value={form.if}
                            onChange={e => set('if', e.target.value)}
                            className="font-mono"
                            placeholder='file.type matches "/^image/"'
                        />
                    </FormRow>
                    <FormRow
                        label={t('integration.needs', 'Needs (runs after)')}
                        htmlFor="integration-needs"
                    >
                        <AsyncCombobox
                            id="integration-needs"
                            multiple
                            queryKey={['integrations', 'needs', needOptions]}
                            loadOptions={async query =>
                                needOptions.filter(o =>
                                    o.label
                                        .toLowerCase()
                                        .includes(query.toLowerCase())
                                )
                            }
                            resolveValue={async v =>
                                needOptions.find(o => o.value === v)
                            }
                            value={form.needs}
                            onChange={v => set('needs', v)}
                            placeholder={t(
                                'integration.needs.placeholder',
                                'Select integrations…'
                            )}
                        />
                    </FormRow>
                </>
            ) : null}
            <FormRow label={t('integration.config', 'Configuration (YAML)')}>
                <CodeEditor
                    mode="twig"
                    minLines={10}
                    value={form.configYaml}
                    onChange={v => set('configYaml', v)}
                />
            </FormRow>
            {reference.data ? (
                <ReferenceDetails
                    sections={[
                        {
                            name: reference.data.displayName,
                            reference: reference.data.reference,
                        },
                        ...reference.data.references,
                    ]}
                />
            ) : null}
            {i?.configInfo && i.configInfo.length > 0 ? (
                <div className="rounded-md border p-3 text-xs">
                    <div className="mb-1 flex items-center gap-2 font-medium">
                        <KeyIcon className="size-4" />{' '}
                        {t('integration.keys', 'Integration keys')}
                    </div>
                    <dl className="space-y-1">
                        {i.configInfo.map(k => (
                            <div
                                key={k.label}
                                className="flex items-center gap-2"
                            >
                                <dt className="w-40 shrink-0 text-muted-foreground">
                                    {k.label}
                                </dt>
                                <dd className="flex min-w-0 flex-1 items-center gap-1 font-mono">
                                    <span className="truncate">{k.value}</span>
                                    {k.value ? (
                                        <CopyButton value={k.value} />
                                    ) : null}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            ) : null}
            {i?.lastErrors && i.lastErrors.length > 0 ? (
                <div className="rounded-md border border-destructive/40 bg-destructive/5 p-2 text-xs">
                    <div className="mb-1 font-semibold text-destructive">
                        {t('attribute_def.last_errors', 'Last errors')}
                    </div>
                    <ul className="space-y-0.5 font-mono">
                        {i.lastErrors.map((e, idx) => (
                            <li key={idx}>
                                {e.date}: {e.message}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
            <div className="flex justify-end">
                <Button
                    onClick={save}
                    loading={saving}
                    disabled={!form.integration}
                >
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
            {i && !form.public ? (
                <div className="border-t pt-4">
                    <h4 className="mb-2 text-sm font-semibold">
                        {t('collection.manage.permissions', 'Permissions')}
                    </h4>
                    <AclEditor
                        objectType={PermissionObject.WorkspaceIntegration}
                        objectId={i.id}
                        definitions={integrationPermissions(t)}
                    />
                </div>
            ) : null}
        </div>
    );
}
