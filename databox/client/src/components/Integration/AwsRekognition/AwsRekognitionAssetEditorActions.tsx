import React, {useEffect, useState} from 'react';
import {AssetIntegrationActionsProps} from "../../Media/Asset/AssetIntegrationActions";
import {Button, List, ListItemButton, ListItemIcon, ListItemText} from "@mui/material";
import {runIntegrationAssetAction} from "../../../api/integrations";
import {IntegrationOverlayCommonProps} from "../../Media/Asset/AssetView";
import VisibilityIcon from '@mui/icons-material/Visibility';
import ImageSearchIcon from '@mui/icons-material/ImageSearch';

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

export default function AwsRekognitionAssetEditorActions({
                                                             asset,
                                                             integration,
                                                             setIntegrationOverlay,
                                                             enableInc,
                                                         }: Props) {
    const [running, setRunning] = useState(false);
    const [instances, setInstances] = useState<Instance[]>([]);

    const data = integration.data;
    const imageLabels = data.find(d => d.name === 'image_labels');

    const process = async () => {
        setRunning(true);
        try {
            const res = await runIntegrationAssetAction('analyze', integration.id, asset.id);
        } catch (e) {
            setRunning(false);
            throw e;
        }
    };

    useEffect(() => {
        if (imageLabels) {
            const labels = JSON.parse(imageLabels.value) as ImageLabel[];
            setInstances(labels
                .filter(d => d.Instances.length > 0)
                .map(d => d.Instances).reduce((d, pr) => pr.concat(d), []));
        }
    }, [imageLabels]);

    useEffect(() => {
        if (imageLabels) {
            setIntegrationOverlay(LabelOverlay, {
                instances,
            });
        }
    }, [enableInc, instances]);

    if (imageLabels) {
        const labels = JSON.parse(imageLabels.value) as ImageLabel[];

        return <List
            component="div"
            disablePadding
        >
            {labels.map(l => {
                return <ListItemButton
                    key={l.Name}
                >
                    <ListItemIcon
                    >
                        <VisibilityIcon/>
                    </ListItemIcon>
                    <ListItemText>
                        {l.Name} <small>({Math.round(l.Confidence * 100) / 100}%)</small>
                    </ListItemText>
                </ListItemButton>
            })}
        </List>
    }

    return <>
        <Button
            onClick={process}
            disabled={running}
            variant={'contained'}
            startIcon={<ImageSearchIcon/>}
        >
            Analyze Image
        </Button>
    </>
}
