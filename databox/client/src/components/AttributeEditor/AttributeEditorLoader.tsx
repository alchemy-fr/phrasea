import {Asset, Workspace} from '../../types.ts';
import {getAssets} from '../../api/asset.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import React from 'react';
import AttributeEditor from './AttributeEditor.tsx';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {WorkspaceContext} from '../../context/WorkspaceContext.tsx';
import {useAttributeDefinitionStore} from '../../store/attributeDefinitionStore.ts';

type Props = {
    ids: string[];
    workspaceId: string;
    onClose: () => void;
};

export default function AttributeEditorLoader({
    ids,
    workspaceId,
    onClose,
}: Props) {
    const [assets, setAssets] = React.useState<Asset[]>();

    const removeFromSelection = React.useCallback((ids: string[]) => {
        setAssets(p => p!.filter(a => !ids.includes(a.id)));
    }, []);

    const loadWorkspaceDefinitions = useAttributeDefinitionStore(
        s => s.loadWorkspace
    );
    const attributeDefinitions = useAttributeDefinitionStore(
        s => s.definitions
    )?.filter(d => (d.workspace as Workspace).id === workspaceId);

    useEffectOnce(() => {
        getAssets({
            ids,
            allLocales: true,
        }).then(r => {
            setAssets(r.result);
        });

        loadWorkspaceDefinitions(workspaceId);
    }, [ids, workspaceId]);

    if (!assets || !attributeDefinitions) {
        return <FullPageLoader />;
    }

    return (
        <WorkspaceContext.Provider
            value={{
                workspaceId,
            }}
        >
            <AttributeEditor
                workspaceId={workspaceId}
                assets={assets}
                attributeDefinitions={attributeDefinitions}
                onClose={onClose}
                removeFromSelection={removeFromSelection}
            />
        </WorkspaceContext.Provider>
    );
}
