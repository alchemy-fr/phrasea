'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {BookOpenIcon, KeyIcon, SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {WorkspaceIntegration} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteIntegration,
    getIntegrationType,
    getIntegrationTypes,
    getWorkspaceIntegrations,
    postIntegration,
    putIntegration,
} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {FormRow, Input, Textarea} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {SimpleSelect} from '@/components/ui/select';
import {Badge} from '@/components/ui/misc';
import {CopyButton} from '@/components/ui/copy-button';
import {AclEditor} from '@/features/permissions/AclEditor';
import {integrationPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {iri} from '@/lib/utils/iri';
import {cn} from '@/lib/utils/cn';

export function IntegrationsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const integrations = useQuery({
        queryKey: ['integrations', 'manage', workspace.id],
        queryFn: () => getWorkspaceIntegrations(workspace.id),
    });
    const types = useQuery({
        queryKey: ['integration-types'],
        queryFn: getIntegrationTypes,
        staleTime: Infinity,
    });

    return (
        <DefinitionManager<WorkspaceIntegration>
            items={integrations.data?.items}
            loading={integrations.isLoading}
            onChanged={() => integrations.refetch()}
            filter={(i, q) =>
                (i.title ?? i.name ?? '').toLowerCase().includes(q) ||
                i.integration.toLowerCase().includes(q)
            }
            renderItem={i => (
                <span
                    className={cn(
                        'flex items-center gap-2',
                        !i.enabled && 'text-destructive'
                    )}
                >
                    <span className="flex-1 truncate">{i.title ?? i.name}</span>
                    <Badge variant="muted">{i.integration}</Badge>
                </span>
            )}
            onDelete={i => deleteIntegration(i.id)}
            createLabel={t('integration.create', 'New integration')}
            renderForm={(i, onSaved) => (
                <IntegrationForm
                    key={i?.id ?? 'new'}
                    integration={i}
                    workspaceId={workspace.id}
                    types={types.data?.items ?? []}
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
    workspaceId: string;
    types: {id: string; name: string; displayName: string}[];
    all: WorkspaceIntegration[];
    onSaved: (i: WorkspaceIntegration) => void;
}) {
    const {t} = useTranslation();
    const [form, setForm] = useState({
        integration: i?.integration ?? '',
        title: i?.title ?? '',
        enabled: i?.enabled ?? true,
        public: i?.public ?? false,
        if: i?.if ?? '',
        needs: i?.needs ?? [],
        configYaml: i?.configYaml ?? '',
    });
    const [saving, setSaving] = useState(false);
    const set = <K extends keyof typeof form>(k: K, v: (typeof form)[K]) =>
        setForm(f => ({...f, [k]: v}));
    const reference = useQuery({
        queryKey: ['integration-type', form.integration],
        queryFn: () => getIntegrationType(form.integration),
        enabled: !!form.integration,
    });

    const save = async () => {
        setSaving(true);
        try {
            const data: Partial<WorkspaceIntegration> = {
                integration: form.integration,
                title: form.title,
                enabled: form.enabled,
                public: form.public,
                if: form.if || undefined,
                needs: form.needs,
                configYaml: form.configYaml,
            };
            const saved = i
                ? await putIntegration(i.id, data)
                : await postIntegration({
                      ...data,
                      workspace: iri(EntityName.Workspace, workspaceId),
                  });
            toast.success(t('integration.saved', 'Integration saved'));
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <FormRow label={t('integration.type', 'Type')}>
                    <SimpleSelect
                        value={form.integration || undefined}
                        onValueChange={v => set('integration', v)}
                        options={types.map(tp => ({
                            value: tp.name,
                            label: tp.displayName ?? tp.name,
                        }))}
                        disabled={!!i}
                        placeholder={t('common.select', 'Select…')}
                    />
                </FormRow>
                <FormRow label={t('integration.title', 'Title')}>
                    <Input
                        value={form.title}
                        onChange={e => set('title', e.target.value)}
                    />
                </FormRow>
            </div>
            <div className="flex flex-wrap gap-6">
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
            <FormRow label={t('integration.if', 'Condition (IF expression)')}>
                <Input
                    value={form.if}
                    onChange={e => set('if', e.target.value)}
                    className="font-mono"
                    placeholder='file.type matches "/^image/"'
                />
            </FormRow>
            <FormRow label={t('integration.needs', 'Needs (runs after)')}>
                <div className="flex flex-wrap gap-2">
                    {all
                        .filter(x => x.id !== i?.id)
                        .map(x => (
                            <LabeledControl
                                key={x.id}
                                label={x.title ?? x.name}
                            >
                                <Switch
                                    checked={form.needs.includes(x['@id'])}
                                    onCheckedChange={v =>
                                        set(
                                            'needs',
                                            v
                                                ? [...form.needs, x['@id']]
                                                : form.needs.filter(
                                                      n => n !== x['@id']
                                                  )
                                        )
                                    }
                                />
                            </LabeledControl>
                        ))}
                </div>
            </FormRow>
            <FormRow label={t('integration.config', 'Configuration (YAML)')}>
                <Textarea
                    value={form.configYaml}
                    onChange={e => set('configYaml', e.target.value)}
                    className="min-h-40 font-mono text-xs"
                    spellCheck={false}
                />
            </FormRow>
            {reference.data ? (
                <details className="rounded-md border p-3 text-xs">
                    <summary className="flex cursor-pointer items-center gap-2 font-medium">
                        <BookOpenIcon className="size-4" />{' '}
                        {t('integration.reference', 'Configuration reference')}
                    </summary>
                    <div className="mt-2 space-y-2">
                        {reference.data.references.map(r => (
                            <div key={r.name}>
                                <div className="flex items-center gap-1 font-semibold">
                                    {r.name} <CopyButton value={r.reference} />
                                </div>
                                {r.description ? (
                                    <p className="text-muted-foreground">
                                        {r.description}
                                    </p>
                                ) : null}
                                <pre className="mt-1 rounded bg-muted p-2 font-mono">
                                    {r.reference}
                                </pre>
                            </div>
                        ))}
                    </div>
                </details>
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
