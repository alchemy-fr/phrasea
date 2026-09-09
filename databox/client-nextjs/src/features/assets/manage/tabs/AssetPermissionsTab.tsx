'use client';

import {useTranslation} from 'react-i18next';
import type {AssetTabProps} from '../AssetManageRoute';
import {AclEditor} from '@/features/permissions/AclEditor';
import {assetPermissions} from '@/features/permissions/permissionDefinitions';
import {PermissionObject} from '@/features/permissions/permissionTypes';
import {ParentAcl} from '@/features/permissions/ParentAcl';

export function AssetPermissionsTab({asset}: AssetTabProps) {
    const {t} = useTranslation();

    return (
        <div className="space-y-6">
            <AclEditor
                objectType={PermissionObject.Asset}
                objectId={asset.id}
                definitions={assetPermissions(t)}
            />
            <ParentAcl
                collection={asset.referenceCollection}
                workspace={asset.workspace}
            />
        </div>
    );
}
