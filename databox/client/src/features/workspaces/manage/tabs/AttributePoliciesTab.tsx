'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import type {AttributePolicy} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {PolicyBadges, PolicyForm} from '../PolicyForm';
import {
    deleteAttributePolicy,
    getAttributePolicies,
    postAttributePolicy,
    putAttributePolicy,
} from '@/lib/api/metadata';
import {attributePolicyPermissions} from '@/features/permissions/permissionDefinitions';
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
                    <PolicyBadges policy={p} />
                </span>
            )}
            onDelete={p => deleteAttributePolicy(p.id)}
            createLabel={t('policy.create', 'New policy')}
            renderForm={(p, onSaved) => (
                <PolicyForm<AttributePolicy>
                    key={p?.id ?? 'new'}
                    policy={p}
                    save={data =>
                        p
                            ? putAttributePolicy(p.id, data)
                            : postAttributePolicy({
                                  ...data,
                                  workspace: iri(
                                      EntityName.Workspace,
                                      workspace.id
                                  ),
                              })
                    }
                    objectType={PermissionObject.AttributePolicy}
                    permissions={attributePolicyPermissions}
                    onSaved={onSaved}
                />
            )}
        />
    );
}
