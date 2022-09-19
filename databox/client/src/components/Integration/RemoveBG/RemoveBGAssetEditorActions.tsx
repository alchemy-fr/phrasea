import React, {useEffect, useState} from 'react';
import {AssetIntegrationActionsProps} from "../../Media/Asset/FileIntegrations";
import {Box, Button} from "@mui/material";
import {runIntegrationFileAction} from "../../../api/integrations";
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
                                                       file,
                                                       integration,
                                                       setIntegrationOverlay,
                                                       enableInc,
                                                   }: Props) {
    const [running, setRunning] = useState(false);
    const [url, setUrl] = useState<string | undefined>(integration.data.find(d => d.name === 'file_url')?.value);

    const process = async () => {
        setRunning(true);
        setUrl((await runIntegrationFileAction('process', integration.id, file.id)).url);
    };

    useEffect(() => {
        if (enableInc && url) {
            setIntegrationOverlay(RemoveBgComparison, {
                left: file.url,
                right: url,
            }, true);
        }
    }, [enableInc, url]);

    if (url) {
        return <IntegrationPanelContent>
            Use slider to compare
        </IntegrationPanelContent>
    }

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
