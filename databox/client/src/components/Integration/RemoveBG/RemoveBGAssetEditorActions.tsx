import React, {useEffect, useState} from 'react';
import {AssetIntegrationActionsProps} from "../../Media/Asset/AssetIntegrationActions";
import {Box, Button} from "@mui/material";
import {runIntegrationAssetAction} from "../../../api/integrations";
import ReactCompareImage from "react-compare-image";
import {IntegrationOverlayCommonProps} from "../../Media/Asset/AssetView";
import AutoFixHighIcon from '@mui/icons-material/AutoFixHigh';
import IntegrationPanelContent from "../Common/IntegrationPanelContent";

type Props = {} & AssetIntegrationActionsProps;

function RemoveBgComparison({
                                left,
                                right,
                                maxDimensions
                            }: {
    left: string;
    right: string;
} & IntegrationOverlayCommonProps) {
    return <div
        style={{
            width: maxDimensions.width,
            maxWidth: maxDimensions.width,
            maxHeight: maxDimensions.height,
        }}
    >
        <ReactCompareImage
            aspectRatio={'taller'}
            leftImage={left}
            rightImage={right}
        />
    </div>;
}

export default function RemoveBGAssetEditorActions({
                                                       asset,
                                                       integration,
                                                       setIntegrationOverlay,
                                                       enableInc,
                                                   }: Props) {
    const [running, setRunning] = useState(false);
    const [result, setResult] = useState<{url: string}>();

    const process = async () => {
        setRunning(true);
        setResult(await runIntegrationAssetAction('process', integration.id, asset.id));
    };

    useEffect(() => {
        if (enableInc && result) {
            setIntegrationOverlay(RemoveBgComparison, {
                left: asset.original?.url,
                right: result.url,
            }, true);
        }
    }, [enableInc, result]);

    return <IntegrationPanelContent>
        <Button
            startIcon={<AutoFixHighIcon/>}
            onClick={process}
            disabled={running}
            variant={'contained'}
        >
            Remove BG
        </Button>
    </IntegrationPanelContent>
}
