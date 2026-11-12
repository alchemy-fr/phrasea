'use client';

import {useTranslation} from 'react-i18next';
import {useQuery} from '@tanstack/react-query';
import type {RenditionPolicy} from '@/types/api';
import {EntityName} from '@/types/api';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {DefinitionManager} from '../DefinitionManager';
import {PolicyBadges, PolicyForm} from '../PolicyForm';
import {
    deleteRenditionPolicy,
    getRenditionPolicies,
    postRenditionPolicy,
    putRenditionPolicy,
} from '@/lib/api/misc';
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
            error={policies.error}
            onChanged={() => policies.refetch()}
            filter={(p, q) => p.name.toLowerCase().includes(q)}
            renderItem={p => <span className="block truncate">{p.name}</span>}
            renderItemDetails={p => <PolicyBadges policy={p} />}
            onDelete={p => deleteRenditionPolicy(p.id)}
            createLabel={t('policy.create', 'New policy')}
            renderForm={(p, onSaved) => (
                <PolicyForm<RenditionPolicy>
                    key={p?.id ?? 'new'}
                    policy={p}
                    save={data =>
                        p
                            ? putRenditionPolicy(p.id, data)
                            : postRenditionPolicy({
                                  ...data,
                                  workspace: iri(
                                      EntityName.Workspace,
                                      workspace.id
                                  ),
                              })
                    }
                    objectType={PermissionObject.RenditionPolicy}
                    permissions={renditionPolicyPermissions}
                    onSaved={onSaved}
                />
            )}
        />
    );
}
