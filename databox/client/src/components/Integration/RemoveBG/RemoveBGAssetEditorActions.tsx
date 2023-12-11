import {useEffect, useState} from 'react';
import {AssetIntegrationActionsProps} from '../../Media/Asset/FileIntegrations';
import {Button, Typography} from '@mui/material';
import {runIntegrationFileAction} from '../../../api/integrations';
import ReactCompareImage from 'react-compare-image';
import {IntegrationOverlayCommonProps} from '../../Media/Asset/AssetView';
import AutoFixHighIcon from '@mui/icons-material/AutoFixHigh';
import IntegrationPanelContent from '../Common/IntegrationPanelContent';
import SaveAsButton from '../../Media/Asset/Actions/SaveAsButton';
import {File} from '../../../types';

function RemoveBgComparison({
    left,
    right,
    maxDimensions,
}: {
    left: string;
    right: string;
} & IntegrationOverlayCommonProps) {
    return (
        <div
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
        </div>
    );
}

type Props = {} & AssetIntegrationActionsProps;

export default function RemoveBGAssetEditorActions({
    asset,
    file,
    integration,
    setIntegrationOverlay,
    enableInc,
}: Props) {
    const [running, setRunning] = useState(false);
    const [bgRemovedFile, setBgRemovedFile] = useState<File | undefined>(
        integration.data.find(d => d.name === 'file')?.value
    );

    const process = async () => {
        setRunning(true);
        setBgRemovedFile(
            await runIntegrationFileAction('process', integration.id, file.id)
        );
    };

    useEffect(() => {
        if (enableInc && bgRemovedFile) {
            setIntegrationOverlay(
                RemoveBgComparison,
                {
                    left: file.url,
                    right: bgRemovedFile.url,
                },
                true
            );
        }
    }, [enableInc, bgRemovedFile]);

    if (bgRemovedFile) {
        return (
            <IntegrationPanelContent>
                <Typography sx={{mb: 3}}>Use slider to compare</Typography>

                <SaveAsButton
                    asset={asset}
                    file={bgRemovedFile}
                    suggestedTitle={asset.resolvedTitle + ' - BG removed'}
                />
            </IntegrationPanelContent>
        );
    }

    return (
        <IntegrationPanelContent>
            <Button
                startIcon={<AutoFixHighIcon />}
                onClick={process}
                disabled={running}
                variant={'contained'}
            >
                Remove BG
            </Button>
        </IntegrationPanelContent>
    );
}
