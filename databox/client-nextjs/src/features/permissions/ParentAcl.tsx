'use client';

import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {ChevronDownIcon} from 'lucide-react';
import type {Collection, Workspace} from '@/types/api';
import {Button} from '@/components/ui/button';
import {AclEditor} from './AclEditor';
import {
    collectionPermissions,
    workspacePermissions,
} from './permissionDefinitions';
import {PermissionObject} from './permissionTypes';
import {CollectionChip, WorkspaceChip} from '@/components/chips';

/**
 * Shows, on demand, the inherited ACLs: the parent collections (recursively)
 * then the workspace.
 */
export function ParentAcl({
    collection,
    workspace,
}: {
    collection?: Collection;
    workspace: Workspace;
}) {
    const {t} = useTranslation();
    const [open, setOpen] = useState(false);

    const chain: Collection[] = [];
    let c: Collection | undefined = collection;
    while (c) {
        chain.push(c);
        c = c.parent;
    }

    return (
        <div className="border-t pt-4">
            <Button variant="ghost" size="sm" onClick={() => setOpen(o => !o)}>
                <ChevronDownIcon
                    className={
                        open
                            ? 'rotate-180 transition-transform'
                            : 'transition-transform'
                    }
                />
                {t('acl.inherited', 'Inherited permissions')}
            </Button>
            {open ? (
                <div className="mt-3 space-y-6">
                    {chain.map(col => (
                        <div key={col.id} className="space-y-2">
                            <CollectionChip
                                collection={col}
                                absolute
                                size="sm"
                            />
                            <AclEditor
                                objectType={PermissionObject.Collection}
                                objectId={col.id}
                                definitions={collectionPermissions(t)}
                                readOnly
                            />
                        </div>
                    ))}
                    <div className="space-y-2">
                        <WorkspaceChip workspace={workspace} size="sm" />
                        <AclEditor
                            objectType={PermissionObject.Workspace}
                            objectId={workspace.id}
                            definitions={workspacePermissions(t)}
                            readOnly
                        />
                    </div>
                </div>
            ) : null}
        </div>
    );
}
