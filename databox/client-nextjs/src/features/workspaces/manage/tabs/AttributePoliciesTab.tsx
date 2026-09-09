'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import {SaveIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {AttributePolicy} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {
    deleteAttributePolicy,
    getAttributePolicies,
    postAttributePolicy,
    putAttributePolicy,
} from '@/lib/api/metadata';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {LabeledControl, Switch} from '@/components/ui/controls';
import {Badge} from '@/components/ui/misc';
import {AclEditor} from '@/features/permissions/AclEditor';
import {renditionPolicyPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {iri} from '@/lib/utils/iri';

export function AttributePoliciesTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();
    const policies = useQuery({
        queryKey: ['attribute-policies', workspace.id],
        queryFn: () => getAttributePolicies(workspace.id),
    });

    return (
        <DefinitionManager<AttributePolicy>
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
                    <Badge variant={p.editable ? 'secondary' : 'muted'}>
                        {p.editable
                            ? t('policy.editable', 'Editable')
                            : t('policy.read_only', 'Read only')}
                    </Badge>
                </span>
            )}
            onDelete={p => deleteAttributePolicy(p.id)}
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
    policy?: AttributePolicy;
    workspaceId: string;
    onSaved: (p: AttributePolicy) => void;
}) {
    const {t} = useTranslation();
    const [name, setName] = useState(policy?.name ?? '');
    const [isPublic, setIsPublic] = useState(policy?.public ?? true);
    const [editable, setEditable] = useState(policy?.editable ?? true);
    const [saving, setSaving] = useState(false);

    const save = async () => {
        setSaving(true);
        try {
            const data = {name, public: isPublic, editable};
            const saved = policy
                ? await putAttributePolicy(policy.id, data)
                : await postAttributePolicy({
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
            <LabeledControl
                label={t('common.public', 'Public')}
                description={t(
                    'policy.public_help',
                    'Visible to everyone who can see the asset. Otherwise, grant access below.'
                )}
            >
                <Switch checked={isPublic} onCheckedChange={setIsPublic} />
            </LabeledControl>
            <LabeledControl label={t('policy.editable', 'Editable')}>
                <Switch checked={editable} onCheckedChange={setEditable} />
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
                        objectType={PermissionObject.AttributePolicy}
                        objectId={policy.id}
                        definitions={renditionPolicyPermissions(t)}
                    />
                </div>
            ) : null}
        </div>
    );
}
