import {useEffect, useState} from 'react';
import {Button, Typography} from '@mui/material';
import {ObjectType, runIntegrationAction} from '../../../api/integrations';
import ReactCompareImage from 'react-compare-image';
import {IntegrationOverlayCommonProps} from '../../Media/Asset/AssetView';
import AutoFixHighIcon from '@mui/icons-material/AutoFixHigh';
import IntegrationPanelContent from '../Common/IntegrationPanelContent';
import SaveAsButton from '../../Media/Asset/Actions/SaveAsButton';
import {useChannelRegistration} from '../../../lib/pusher.ts';
import {useIntegrationData} from '../useIntegrationData.ts';
import {AssetIntegrationActionsProps, Integration} from '../types.ts';
import {useTranslation} from 'react-i18next';

function RemoveBgComparison({
    left,
    right,
    dimensions,
}: {
    left: string;
    right: string;
} & IntegrationOverlayCommonProps) {

    return (
        <div
            style={{
                width: dimensions.width,
                maxWidth: dimensions.width,
                maxHeight: dimensions.height,
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
    const {t} = useTranslation();
    const [running, setRunning] = useState(false);
    const {data, load: loadData} = useIntegrationData({
        objectType: ObjectType.File,
        objectId: file.id,
        integrationId: integration.id,
        defaultData: integration.data,
    });

    const bgRemovedFile = data.pages.flat().find(d => d.name === 'file')?.value;

    const process = async () => {
        setRunning(true);
        await runIntegrationAction('process', integration.id, {
            fileId: file.id,
        });
    };

    useChannelRegistration(
        `file-${file.id}`,
        `integration:${Integration.RemoveBg}`,
        () => {
            setRunning(false);
            loadData();
        }
    );

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
                <Typography sx={{mb: 3}}>
                    {t(
                        'remove_bgasset_editor_actions.use_slider_to_compare',
                        `Use slider to compare`
                    )}
                </Typography>

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
                {t('remove_bgasset_editor_actions.remove_bg', `Remove BG`)}
            </Button>
        </IntegrationPanelContent>
    );
}
