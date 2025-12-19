import React, {FC, useEffect, useRef, useState} from 'react';
import {Asset, ApiFile, WorkspaceIntegration} from '../../../types';
import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    Skeleton,
    Typography,
} from '@mui/material';
import {
    getIntegrationsOfContext,
    IntegrationContext,
    ObjectType,
} from '../../../api/integrations';
import RemoveBGAssetEditorActions from '../../Integration/RemoveBG/RemoveBGAssetEditorActions';
import {SetIntegrationOverlayFunction} from './View/AssetView.tsx';
import AwsRekognitionAssetEditorActions from '../../Integration/AwsRekognition/AwsRekognitionAssetEditorActions';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import TUIPhotoEditor from '../../Integration/TuiPhotoEditor/TUIPhotoEditor';
import {
    AssetIntegrationActionsProps,
    Integration,
} from '../../Integration/types.ts';
import {AssetAnnotationRef} from './Annotations/annotationTypes.ts';
import MatomoAssetEditorActions from '../../Integration/Matomo/MatomoAssetEditorActions';

const supportsImage = (file: ApiFile): boolean => {
    return (file && file.type.startsWith('image/')) || false;
};

const supportsAll = (_file: ApiFile): boolean => {
    return true;
};

const integrations: Record<
    string,
    {
        component: FC<AssetIntegrationActionsProps>;
        supports: (file: ApiFile) => boolean;
    }
> = {
    [Integration.RemoveBg]: {
        component: RemoveBGAssetEditorActions,
        supports: supportsImage,
    },
    [Integration.AwsRekognition]: {
        component: AwsRekognitionAssetEditorActions,
        supports: supportsImage,
    },
    [Integration.TuiPhotoEditor]: {
        component: TUIPhotoEditor,
        supports: supportsImage,
    },
    [Integration.Matomo]: {
        component: MatomoAssetEditorActions,
        supports: supportsAll,
    },
};

function IntegrationProxy({
    onExpand,
    ...props
}: {
    onExpand: () => void;
} & AssetIntegrationActionsProps) {
    const i = props.integration.integration;

    // eslint-disable-next-line no-prototype-builtins
    if (
        Object.hasOwnProperty.call(integrations, i) &&
        integrations[i].supports(props.file)
    ) {
        return (
            <Accordion expanded={props.expanded} onChange={onExpand}>
                <AccordionSummary
                    expandIcon={<ExpandMoreIcon />}
                    aria-controls="panel1a-content"
                    id="panel1a-header"
                >
                    <Typography component="div">
                        {props.integration.title}
                    </Typography>
                </AccordionSummary>
                <AccordionDetails
                    sx={{
                        p: 0,
                    }}
                >
                    {React.createElement(integrations[i].component, props)}
                </AccordionDetails>
            </Accordion>
        );
    }

    return <></>;
}

type Props = {
    asset: Asset;
    file: ApiFile;
    setIntegrationOverlay: SetIntegrationOverlayFunction;
    assetAnnotationsRef?: AssetAnnotationRef;
};

export default function FileIntegrations({
    asset,
    file,
    setIntegrationOverlay,
    assetAnnotationsRef,
}: Props) {
    const [integrations, setIntegrations] = useState<WorkspaceIntegration[]>();
    const [expanded, setExpanded] = useState<string>();
    const enableIncs = useRef<Record<string, number>>({});

    useEffect(() => {
        setExpanded(undefined);
        getIntegrationsOfContext(
            IntegrationContext.AssetView,
            asset.workspace.id,
            {
                objectType: ObjectType.File,
                objectId: file.id,
                enabled: true,
            }
        ).then(r => setIntegrations(r.result));
    }, [file.id]);

    useEffect(() => {
        if (!expanded) {
            setIntegrationOverlay(null);
            assetAnnotationsRef?.current?.replaceAnnotations([]);
        }
    }, [expanded, integrations]);

    return (
        <>
            {!integrations && (
                <div>
                    <Skeleton height={70} />
                </div>
            )}
            {integrations &&
                integrations.map(i => (
                    <IntegrationProxy
                        key={i.id}
                        expanded={expanded === i.id}
                        onExpand={() => {
                            enableIncs.current[i.id] = enableIncs.current[i.id]
                                ? enableIncs.current[i.id] + 1
                                : 1;
                            setExpanded(p => (p === i.id ? undefined : i.id));
                        }}
                        integration={i}
                        asset={asset}
                        file={file}
                        enableInc={enableIncs.current[i.id]}
                        setIntegrationOverlay={setIntegrationOverlay}
                        assetAnnotationsRef={assetAnnotationsRef}
                    />
                ))}
        </>
    );
}
