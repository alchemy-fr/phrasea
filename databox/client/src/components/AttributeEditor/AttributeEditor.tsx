import {Asset, AttributeDefinition} from "../../types.ts";
import {useTranslation} from "react-i18next";
import {getAssets} from "../../api/asset.ts";
import {Box, Typography} from "@mui/material";
import {FullPageLoader} from "@alchemy/phrasea-ui";
import React from "react";
import ThumbList from "./ThumbList.tsx";
import {AssetSelectionContext} from "../../context/AssetSelectionContext.tsx";
import DisplayProvider from "../Media/DisplayProvider.tsx";
import {OnToggle} from "../AssetList/types.ts";
import {getItemListFromEvent} from "../AssetList/selection.ts";
import Attributes from "./Attributes.tsx";
import {getWorkspaceAttributeDefinitions} from "../../api/attributes.ts";

type Props = {
    ids: string[];
};

export default function AttributeEditor({
    ids,
}: Props) {
    const {t} = useTranslation();
    const [assets, setAssets] = React.useState<Asset[]>();
    const [subSelection, setSubSelection] = React.useState<Asset[]>([]);
    const [attributeDefinitions, setAttributeDefinitions] = React.useState<AttributeDefinition[]>();

    const onToggleAsset = React.useCallback<OnToggle<Asset>>(
        (asset, e): void => {
            e?.preventDefault();
            setSubSelection(prev => {
                return getItemListFromEvent(prev, asset, [assets!], e);
            });
        },
        [assets]
    );

    React.useEffect(() => {
        getAssets({
            ids,
        }).then(r => {
            setAssets(r.result);
            setSubSelection(r.result);
        });
    }, [ids]);

    React.useEffect(() => {
        if (assets) {
            getWorkspaceAttributeDefinitions(assets[0].workspace.id).then(r => {
                setAttributeDefinitions(r);
            });
        }
    }, [assets]);

    if (!assets) {
        return <FullPageLoader/>
    }

    return <Box
        sx={{
            height: '100vh',
            overflow: 'hidden',
        }}
    >
        <Typography variant={'h1'}>
            {t('attribute.editor.title', 'Attribute Editor')}
        </Typography>
        <DisplayProvider>
            <AssetSelectionContext.Provider
                value={{
                    selection: subSelection!,
                    setSelection: setSubSelection,
                }}
            >
                <ThumbList
                    assets={assets}
                    onToggle={onToggleAsset}
                    subSelection={subSelection}
                />

                {attributeDefinitions ? <Attributes
                    attributeDefinitions={attributeDefinitions}
                    assets={assets}
                    subSelection={subSelection}
                /> : <>Loading attributes...</>}
            </AssetSelectionContext.Provider>
        </DisplayProvider>
    </Box>
}
