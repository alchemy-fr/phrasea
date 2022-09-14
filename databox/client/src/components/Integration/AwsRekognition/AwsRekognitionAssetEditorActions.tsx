import React, {useEffect, useState} from 'react';
import {AssetIntegrationActionsProps} from "../../Media/Asset/AssetIntegrations";
import {Button, List, ListItemButton, ListItemIcon, ListItemText} from "@mui/material";
import {runIntegrationAssetAction} from "../../../api/integrations";
import {IntegrationOverlayCommonProps} from "../../Media/Asset/AssetView";
import VisibilityIcon from '@mui/icons-material/Visibility';
import ImageSearchIcon from '@mui/icons-material/ImageSearch';
import {WorkspaceIntegration} from "../../../types";
import IntegrationPanelContent from "../Common/IntegrationPanelContent";

type Props = {} & AssetIntegrationActionsProps;

type BoundingBox = {
    Width: number;
    Height: number;
    Top: number;
    Left: number;
}

type Instance = {
    BoundingBox: BoundingBox;
    Confidence: number;
};

type Data = ImageLabel[];

type ImageLabel = {
    Name: string;
    Confidence: number;
    Instances: Instance[];
    Parents: {
        Name: string;
    }[];
};

function LabelOverlay({
                          instances,
                      }: {
    instances: Instance[];
} & IntegrationOverlayCommonProps) {
    return <div>
        {instances.map((i, k) => {
            const box = i.BoundingBox;

            const percent = (x: number) => `${x * 100}%`;

            return <div
                key={k}
                style={{
                    position: 'absolute',
                    top: percent(box.Top),
                    left: percent(box.Left),
                    width: percent(box.Width),
                    height: percent(box.Height),
                    boxShadow: `0 0 3px blue, 0 0 3px inset blue`,
                }}
            ></div>
        })}
    </div>
}

function parseData(integration: WorkspaceIntegration): Data | undefined {
    const value = integration.data.find(d => d.name === 'image_labels');

    if (!value) {
        return;
    }

    return JSON.parse(value.value) as Data;
}

export default function AwsRekognitionAssetEditorActions({
                                                             asset,
                                                             integration,
                                                             setIntegrationOverlay,
                                                             enableInc,
                                                         }: Props) {
    const [running, setRunning] = useState(false);
    const [data, setData] = useState<Data | undefined>();
    const [instances, setInstances] = useState<Instance[]>([]);

    const process = async () => {
        setRunning(true);
        try {
            setData(await runIntegrationAssetAction('analyze', integration.id, asset.id));
        } catch (e) {
            setRunning(false);
            throw e;
        }
    };

    useEffect(() => {
        const d = parseData(integration);
        if (d) {
            setData(d);
        }
    }, [integration.data]);

    useEffect(() => {
        if (data) {
            setInstances(data
                .filter(d => d.Instances.length > 0)
                .map(d => d.Instances).reduce((d, pr) => pr.concat(d), []));
        }
    }, [data]);

    useEffect(() => {
        if (instances) {
            setIntegrationOverlay(LabelOverlay, {
                instances,
            });
        }
    }, [enableInc, instances]);

    if (data) {
        return <List
            component="div"
            disablePadding
        >
            {data.map(l => {
                return <ListItemButton
                    key={l.Name}
                >
                    <ListItemText>
                        {l.Name} <small>({Math.round(l.Confidence * 100) / 100}%)</small>
                    </ListItemText>
                    {l.Instances.length > 0 && <ListItemIcon
                    >
                        <VisibilityIcon/>
                    </ListItemIcon>}
                </ListItemButton>
            })}
        </List>
    }

    return <IntegrationPanelContent>
        <Button
            onClick={process}
            disabled={running}
            variant={'contained'}
            startIcon={<ImageSearchIcon/>}
        >
            Analyze Image
        </Button>
    </IntegrationPanelContent>
}
