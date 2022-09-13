import React, {FC, useEffect, useRef, useState} from 'react';
import {Asset, WorkspaceIntegration} from "../../../types";
import {
    Accordion,
    AccordionDetails,
    AccordionSummary,
    CircularProgress,
    Collapse,
    List,
    ListItemButton,
    ListItemText,
    Typography
} from "@mui/material";
import {getWorkspaceIntegrations} from "../../../api/integrations";
import RemoveBGAssetEditorActions from "../../Integration/RemoveBG/RemoveBGAssetEditorActions";
import {SetIntegrationOverlayFunction} from "./AssetView";
import AwsRekognitionAssetEditorActions from "../../Integration/AwsRekognition/AwsRekognitionAssetEditorActions";
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';

export type AssetIntegrationActionsProps = {
    asset: Asset;
    integration: WorkspaceIntegration;
    setIntegrationOverlay: SetIntegrationOverlayFunction;
    enableInc: number;
}

const integrations: Record<string, FC<AssetIntegrationActionsProps>> = {
    'remove.bg': RemoveBGAssetEditorActions,
    'aws.rekognition': AwsRekognitionAssetEditorActions,
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
                <Typography>{props.integration.title}</Typography>
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
    setIntegrationOverlay: SetIntegrationOverlayFunction;
};

export default function AssetIntegrationActions({
                                                    asset,
                                                    setIntegrationOverlay,
                                                }: Props) {
    const [integrations, setIntegrations] = useState<WorkspaceIntegration[]>();
    const [expanded, setExpanded] = useState<string>();
    const enableIncs = useRef<Record<string, number>>({});

    useEffect(() => {
        getWorkspaceIntegrations(asset.workspace.id, asset.id).then(r => setIntegrations(r.result));
    }, []);

    return <>
        {!integrations && <CircularProgress color="inherit"/>}
        {integrations && <List
            component="nav"
            aria-labelledby="nested-list-subheader"
        >
            {integrations.map(i => <IntegrationProxy
                expanded={expanded === i.id}
                onExpand={() => {
                    enableIncs.current[i.id] = enableIncs.current[i.id] ? enableIncs.current[i.id] + 1 : 1;
                    setExpanded(p => p === i.id ? undefined : i.id)
                }}
                key={i.id}
                integration={i}
                asset={asset}
                enableInc={enableIncs.current[i.id]}
                setIntegrationOverlay={setIntegrationOverlay}
            />)}
        </List>}
    </>
}
