'use client';

import {useWorkspace} from './collectionStore';

/** Name of a workspace by id, loaded when not known yet */
export function WorkspaceName({id}: {id: string}) {
    const workspace = useWorkspace(id);

    return <>{workspace ? (workspace.displayName ?? workspace.name) : ''}</>;
}
