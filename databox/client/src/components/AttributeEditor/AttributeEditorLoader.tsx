import {Asset, AttributeDefinition} from '../../types.ts';
import {getAssets} from '../../api/asset.ts';
import {FullPageLoader} from '@alchemy/phrasea-ui';
import React from 'react';
import {getWorkspaceAttributeDefinitions} from '../../api/attributes.ts';
import AttributeEditor from './AttributeEditor.tsx';
import useEffectOnce from '@alchemy/react-hooks/src/useEffectOnce';
import {WorkspaceContext} from "../../context/WorkspaceContext.tsx";

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
    const [attributeDefinitions, setAttributeDefinitions] =
        React.useState<AttributeDefinition[]>();

    const removeFromSelection = React.useCallback((ids: string[]) => {
        setAssets(p => p!.filter(a => !ids.includes(a.id)));
    }, []);

    useEffectOnce(() => {
        getAssets({
            ids,
            allLocales: true,
        }).then(r => {
            setAssets(r.result);
        });

        getWorkspaceAttributeDefinitions(workspaceId).then(r => {
            setAttributeDefinitions(r);
        });
    }, [ids, workspaceId]);

    if (!assets || !attributeDefinitions) {
        return <FullPageLoader/>;
    }

    return (
        <WorkspaceContext.Provider value={{
            workspaceId,
        }}>
            <AttributeEditor
                assets={assets}
                attributeDefinitions={attributeDefinitions}
                onClose={onClose}
                removeFromSelection={removeFromSelection}
            />
        </WorkspaceContext.Provider>
    );
}
