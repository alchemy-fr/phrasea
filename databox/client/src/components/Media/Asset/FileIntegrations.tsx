import React, {FC, useCallback, useEffect, useRef, useState} from 'react';
import {Asset, File, WorkspaceIntegration} from "../../../types";
import {Accordion, AccordionDetails, AccordionSummary, CircularProgress, List, Typography} from "@mui/material";
import {getWorkspaceIntegrations} from "../../../api/integrations";
import RemoveBGAssetEditorActions from "../../Integration/RemoveBG/RemoveBGAssetEditorActions";
import {SetIntegrationOverlayFunction} from "./AssetView";
import AwsRekognitionAssetEditorActions from "../../Integration/AwsRekognition/AwsRekognitionAssetEditorActions";
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import TUIPhotoEditor from "../../Integration/TuiPhotoEditor/TUIPhotoEditor";

export type AssetIntegrationActionsProps = {
    asset: Asset;
    file: File;
    integration: WorkspaceIntegration;
    setIntegrationOverlay: SetIntegrationOverlayFunction;
    enableInc: number;
    refreshIntegrations: () => Promise<void>;
}

const integrations: Record<string, FC<AssetIntegrationActionsProps>> = {
    'remove.bg': RemoveBGAssetEditorActions,
    'aws.rekognition': AwsRekognitionAssetEditorActions,
    'tui.photo-editor': TUIPhotoEditor,
}

function IntegrationProxy({
                              expanded,
                              onExpand,
                              ...props
                          }: {
    expanded: boolean;
    onExpand: () => void;
} & AssetIntegrationActionsProps) {
    const i = props.integration.integration;

    if (integrations.hasOwnProperty(i)) {
        return <Accordion
            expanded={expanded}
            onChange={onExpand}
        >
            <AccordionSummary
                expandIcon={<ExpandMoreIcon/>}
                aria-controls="panel1a-content"
                id="panel1a-header"
            >
                <Typography component="div">{props.integration.title}</Typography>
            </AccordionSummary>
            <AccordionDetails
                sx={{
                    p: 0,
                }}
            >
                {React.createElement(integrations[i], props)}
            </AccordionDetails>
        </Accordion>
    }

    return <></>
}

type Props = {
    asset: Asset;
    file: File;
    setIntegrationOverlay: SetIntegrationOverlayFunction;
};

export default function FileIntegrations({
                                             asset,
                                             file,
                                             setIntegrationOverlay,
                                         }: Props) {
    const [integrations, setIntegrations] = useState<WorkspaceIntegration[]>();
    const [expanded, setExpanded] = useState<string>();
    const enableIncs = useRef<Record<string, number>>({});

    useEffect(() => {
        setExpanded(undefined);
        getWorkspaceIntegrations(file.id).then(r => setIntegrations(r.result));
    }, [file.id]);

    useEffect(() => {
        if (!expanded) {
            setIntegrationOverlay(() => <></>, {}, false);
        }
    }, [expanded, integrations]);

    const refreshIntegrations = useCallback(async () => {
        const r = await getWorkspaceIntegrations(file.id)
        setIntegrations(r.result);
    }, [file.id]);

    return <>
        {!integrations && <CircularProgress color="inherit"/>}
        {integrations && <List
            component="nav"
            aria-labelledby="nested-list-subheader"
        >
            {integrations.filter(i => i.supported).map(i => <IntegrationProxy
                refreshIntegrations={refreshIntegrations}
                expanded={expanded === i.id}
                onExpand={() => {
                    enableIncs.current[i.id] = enableIncs.current[i.id] ? enableIncs.current[i.id] + 1 : 1;
                    setExpanded(p => p === i.id ? undefined : i.id)
                }}
                key={i.id}
                integration={i}
                asset={asset}
                file={file}
                enableInc={enableIncs.current[i.id]}
                setIntegrationOverlay={setIntegrationOverlay}
            />)}
        </List>}
    </>
}
