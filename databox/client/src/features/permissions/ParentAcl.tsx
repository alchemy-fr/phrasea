'use client';

import {ReactNode, useState} from 'react';
import {useTranslation} from 'react-i18next';
import Link from 'next/link';
import {useQuery} from '@tanstack/react-query';
import {ArrowRightIcon, ChevronDownIcon} from 'lucide-react';
import type {Collection, Workspace} from '@/types/api';
import {Button} from '@alchemy/next-ui/ui/button';
import {getCollection, getWorkspace} from '@/lib/api/collections';
import {routes} from '@/lib/routes';
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
                            <CollectionAclTitle collection={col} />
                            <AclEditor
                                objectType={PermissionObject.Collection}
                                objectId={col.id}
                                definitions={collectionPermissions(t)}
                                readOnly
                            />
                        </div>
                    ))}
                    <div className="space-y-2">
                        <WorkspaceAclTitle workspace={workspace} />
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

/*
 * The collections and the workspace nested in an asset have no capabilities:
 * they are loaded with the query of their manage dialog, which shares its cache.
 */

function CollectionAclTitle({collection}: {collection: Collection}) {
    const {data} = useQuery({
        queryKey: ['collection', collection.id],
        queryFn: () => getCollection(collection.id),
        retry: false,
    });

    return (
        <AclTitle
            href={
                data?.capabilities.editPermissions
                    ? routes.collectionManage(collection.id, 'permissions')
                    : undefined
            }
        >
            <CollectionChip collection={collection} absolute size="sm" />
        </AclTitle>
    );
}

function WorkspaceAclTitle({workspace}: {workspace: Workspace}) {
    const {data} = useQuery({
        queryKey: ['workspace', workspace.id],
        queryFn: () => getWorkspace(workspace.id),
        retry: false,
    });

    return (
        <AclTitle
            href={
                data?.capabilities.editPermissions
                    ? routes.workspaceManage(workspace.id, 'permissions')
                    : undefined
            }
        >
            <WorkspaceChip workspace={workspace} size="sm" />
        </AclTitle>
    );
}

/** Link to the permissions of the parent, when the user can manage them */
function AclTitle({href, children}: {href?: string; children: ReactNode}) {
    const {t} = useTranslation();

    return (
        <div className="flex items-center justify-between gap-2">
            {children}
            {href ? (
                <Link
                    href={href}
                    className="inline-flex shrink-0 items-center gap-1 text-xs text-primary hover:underline"
                >
                    {t('acl.manage_inherited', 'Manage permissions')}
                    <ArrowRightIcon className="size-3.5" />
                </Link>
            ) : null}
        </div>
    );
}
