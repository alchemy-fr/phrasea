import React from 'react';
import {useCollectionStore} from '../../store/collectionStore';
import WorkspaceMenuItem from './WorkspaceMenuItem';
import {getWorkspaces} from '../../api/collection';
import {Workspace} from '../../types';

type Props = {};

function CollectionsPanel({}: Props) {
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

export default React.memo(CollectionsPanel);
