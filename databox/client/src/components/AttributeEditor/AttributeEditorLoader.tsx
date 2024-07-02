import {Asset, AttributeDefinition} from "../../types.ts";
import {getAssets} from "../../api/asset.ts";
import {FullPageLoader} from "@alchemy/phrasea-ui";
import React from "react";
import {getWorkspaceAttributeDefinitions} from "../../api/attributes.ts";
import AttributeEditor from "./AttributeEditor.tsx";

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
    const [attributeDefinitions, setAttributeDefinitions] = React.useState<AttributeDefinition[]>();

    React.useEffect(() => {
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
        return <FullPageLoader/>
    }

    return <AttributeEditor
        assets={assets}
        attributeDefinitions={attributeDefinitions}
        onClose={onClose}
    />
}
