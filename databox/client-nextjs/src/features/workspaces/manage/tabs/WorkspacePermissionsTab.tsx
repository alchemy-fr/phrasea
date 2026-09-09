'use client';

import {useTranslation} from 'react-i18next';
import type {WorkspaceTabProps} from '../WorkspaceManageRoute';
import {AclEditor} from '@/features/permissions/AclEditor';
import {workspacePermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';

export function WorkspacePermissionsTab({workspace}: WorkspaceTabProps) {
    const {t} = useTranslation();

    return (
        <AclEditor
            objectType={PermissionObject.Workspace}
            objectId={workspace.id}
            definitions={workspacePermissions(t)}
        />
    );
}
