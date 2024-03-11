import React from 'react';
import {useCollectionStore} from '../../store/collectionStore.ts';
import WorkspaceMenuItem from './WorkspaceMenuItem.tsx';
import {getWorkspaces} from '../../api/collection.ts';
import {Workspace} from '../../types.ts';

type Props = {};

export default function CollectionsPanel({}: Props) {
    const [workspaces, setWorkspaces] = React.useState<Workspace[]>([]);

    const setRootCollections = useCollectionStore(
        state => state.setRootCollections
    );

    React.useEffect(() => {
        getWorkspaces().then(result => {
            setRootCollections(result);
            setWorkspaces(result);
        });
    }, []);

    return (
        <>
            {workspaces.map(w => (
                <WorkspaceMenuItem data={w} key={w.id} />
            ))}
        </>
    );
}
