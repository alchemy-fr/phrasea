'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {RenditionPolicy} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteRenditionPolicy,
    getRenditionPolicies,
    postRenditionPolicy,
    putRenditionPolicy,
} from '@/lib/api/misc';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {Badge} from '@/components/ui/misc';
import {AclEditor} from '@/features/permissions/AclEditor';
import {renditionPolicyPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {iri} from '@/lib/utils/iri';

export function RenditionPoliciesTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const policies = useQuery({
        queryKey: ['rendition-policies', workspace.id],
        queryFn: () => getRenditionPolicies(workspace.id),
    });

    return (
        <DefinitionManager<RenditionPolicy>
            items={policies.data?.items}
            loading={policies.isLoading}
            onChanged={() => policies.refetch()}
            filter={(p, q) => p.name.toLowerCase().includes(q)}
            renderItem={p => (
                <span className="flex items-center gap-2">
                    <span className="flex-1 truncate">{p.name}</span>
                    <Badge variant={p.public ? 'success' : 'muted'}>
                        {p.public
                            ? t('common.public', 'Public')
                            : t('common.private', 'Private')}
                    </Badge>
                </span>
            )}
            onDelete={p => deleteRenditionPolicy(p.id)}
            createLabel={t('policy.create', 'New policy')}
            renderForm={(p, onSaved) => (
                <PolicyForm
                    key={p?.id ?? 'new'}
                    policy={p}
                    workspaceId={workspace.id}
                    onSaved={onSaved}
                />
            )}
        />
    );
}

function PolicyForm({
    policy,
    workspaceId,
    onSaved,
}: {
    policy?: RenditionPolicy;
    workspaceId: string;
    onSaved: (p: RenditionPolicy) => void;
}) {
    const {t} = useTranslation();
    const [name, setName] = useState(policy?.name ?? '');
    const [isPublic, setIsPublic] = useState(policy?.public ?? true);
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const data = {name, public: isPublic};
            const saved = policy
                ? await putRenditionPolicy(policy.id, data)
                : await postRenditionPolicy({
                      ...data,
                      workspace: iri(EntityName.Workspace, workspaceId),
                  });
            toast.success(t('policy.saved', 'Policy saved'));
            onSaved(saved);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-4">
            <FormRow label={t('common.name', 'Name')}>
                <Input value={name} onChange={e => setName(e.target.value)} />
            </FormRow>
            <LabeledControl label={t('common.public', 'Public')}>
                <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            </LabeledControl>
            <div className="flex justify-end">
                <Button onClick={save} loading={saving} disabled={!name.trim()}>
                    <SaveIcon /> {t('common.save', 'Save')}
                </Button>
            </div>
            {policy && !isPublic ? (
                <div className="border-t pt-4">
                    <h4 className="mb-2 text-sm font-semibold">
                        {t('collection.manage.permissions', 'Permissions')}
                    </h4>
                    <AclEditor
                        objectType={PermissionObject.RenditionPolicy}
                        objectId={policy.id}
                        definitions={renditionPolicyPermissions(t)}
                    />
                </div>
            ) : null}
        </div>
    );
}
