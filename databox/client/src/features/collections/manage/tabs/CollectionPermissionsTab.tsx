'use client';

import {useTranslation} from 'react-i18next';
import type {CollectionTabProps} from '../CollectionManageRoute';
import {AclEditor} from '@/features/permissions/AclEditor';
import {collectionPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {ParentAcl} from '@/features/permissions/ParentAcl';

export function CollectionPermissionsTab({collection}: CollectionTabProps) {
    const {t} = useTranslation();

    return (
        <div className="space-y-6">
            <AclEditor
                objectType={PermissionObject.Collection}
                objectId={collection.id}
                definitions={collectionPermissions(t)}
            />
            <ParentAcl
                collection={collection.parent}
                workspace={collection.workspace}
            />
        </div>
    );
}
