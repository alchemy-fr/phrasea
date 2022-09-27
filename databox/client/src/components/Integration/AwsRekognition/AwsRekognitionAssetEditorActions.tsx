import React, {useEffect, useState} from 'react';
import {AssetIntegrationActionsProps} from "../../Media/Asset/FileIntegrations";
import {Button, List, ListItem, ListItemButton, ListItemIcon, ListItemText, ListSubheader} from "@mui/material";
import {runIntegrationFileAction} from "../../../api/integrations";
import {IntegrationOverlayCommonProps} from "../../Media/Asset/AssetView";
import VisibilityIcon from '@mui/icons-material/Visibility';
import ImageSearchIcon from '@mui/icons-material/ImageSearch';
import {WorkspaceIntegration} from "../../../types";
import IntegrationPanelContent from "../Common/IntegrationPanelContent";

type BoundingBox = {
    Width: number;
    Height: number;
    Top: number;
    Left: number;
}

enum DetectType {
    Labels = 'labels',
    Texts = 'texts',
    Faces = 'faces',
}

type Instance = {
    BoundingBox: BoundingBox;
    Confidence: number;
};

type LabelsData = {
    Labels: ImageLabel[];
};

type TextsData = {
    TextDetections: TextDetection[];
};

type FacesData = {
    FaceDetails: FaceDetail[];
};

type ValueConfidence<T> = {
    Value: T;
    Confidence: number;
}

type FaceDetail = {
    Confidence: number;
    BoundingBox: BoundingBox;
    AgeRange: {
        Low: number;
        High: number;
    };
    Smile: ValueConfidence<boolean>;
    Eyeglasses: ValueConfidence<boolean>;
    Gender: ValueConfidence<"Male" | "Female">;
    Beard: ValueConfidence<boolean>;
    Mustache: ValueConfidence<boolean>;
    EyesOpen: ValueConfidence<boolean>;
    MouthOpen: ValueConfidence<boolean>;
    Emotions: {
        Type: string;
        Confidence: number;
    }[];
};

type Polygon = {
    X: number;
    Y: number;
}

type TextDetection = {
    Id: string;
    ParentId: string;
    DetectedText: string;
    Type: "LINE" | "WORD";
    Confidence: number;
    Geometry: {
        BoundingBox: BoundingBox;
    };
    Polygon: Polygon[];
};

type ImageLabel = {
    Name: string;
    Confidence: number;
    Instances: Instance[];
    Parents: {
        Name: string;
    }[];
};

function ImageOverlay({
                          instances,
                          texts,
                          faces
                      }: {
    instances: Instance[] | undefined;
    texts: TextDetection[] | undefined;
    faces: FaceDetail[] | undefined;
} & IntegrationOverlayCommonProps) {
    return <div>
        {instances && instances.map((i, k) => {
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
        {texts && texts.map((i, k) => {
            const box = i.Geometry.BoundingBox;

            const percent = (x: number) => `${x * 100}%`;

            return <div
                key={k}
                style={{
                    position: 'absolute',
                    top: percent(box.Top),
                    left: percent(box.Left),
                    width: percent(box.Width),
                    height: percent(box.Height),
                    boxShadow: `0 0 3px red, 0 0 3px inset red`,
                }}
            ></div>
        })}
        {faces && faces.map((i, k) => {
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
                    boxShadow: `0 0 3px yellow, 0 0 3px inset yellow`,
                }}
            ></div>
        })}
    </div>
}

function parseData<T>(integration: WorkspaceIntegration, key: string): T | undefined {
    const value = integration.data.find(d => d.name === key);

    if (!value) {
        return;
    }

    return JSON.parse(value.value) as T;
}

type Props = {} & AssetIntegrationActionsProps;

export default function AwsRekognitionAssetEditorActions({
                                                             file,
                                                             integration,
                                                             setIntegrationOverlay,
                                                             enableInc,
                                                         }: Props) {
    const [running, setRunning] = useState<DetectType | undefined>();
    const [labels, setLabels] = useState<LabelsData | undefined>();
    const [texts, setTexts] = useState<TextsData | undefined>();
    const [faces, setFaces] = useState<FacesData | undefined>();
    const [instances, setInstances] = useState<Instance[]>([]);

    const process = async (category: DetectType) => {
        setRunning(category);
        try {
            const res = await runIntegrationFileAction('analyze', integration.id, file.id, {
                category
            });

            switch (category) {
                case DetectType.Labels:
                    setLabels(res[DetectType.Labels]);
                    break;
                case DetectType.Texts:
                    setTexts(res[DetectType.Texts]);
                    break;
                case DetectType.Faces:
                    setFaces(res[DetectType.Faces]);
                    break;
            }

        } catch (e) {
            setRunning(undefined);
            throw e;
        }
    };

    useEffect(() => {
        setLabels(parseData(integration, 'labels'));
        setTexts(parseData(integration, 'texts'));
        setFaces(parseData(integration, 'faces'));
    }, [integration.data]);

    useEffect(() => {
        if (labels) {
            setInstances(labels.Labels
                .filter(d => d.Instances.length > 0)
                .map(d => d.Instances).reduce((d, pr) => pr.concat(d), []));
        }
    }, [labels]);

    useEffect(() => {
        if (instances || texts || faces) {
            setIntegrationOverlay(ImageOverlay, {
                instances,
                texts: texts?.TextDetections,
                faces: faces?.FaceDetails,
            });
        }
    }, [enableInc, instances, texts]);

    const options = integration.options as {
        labels: boolean;
        texts: boolean;
        faces: boolean;
    };

    return <>
        {options.labels && !labels && <IntegrationPanelContent>
            <Button
                onClick={() => process(DetectType.Labels)}
                disabled={running === DetectType.Labels}
                variant={'contained'}
                startIcon={<ImageSearchIcon/>}
            >
                Detect image labels
            </Button>
        </IntegrationPanelContent>}
        {options.texts && !texts && <IntegrationPanelContent>
            <Button
                onClick={() => process(DetectType.Texts)}
                disabled={running === DetectType.Texts}
                variant={'contained'}
                startIcon={<ImageSearchIcon/>}
            >
                Detect texts
            </Button>
        </IntegrationPanelContent>}
        {options.faces && !faces && <IntegrationPanelContent>
            <Button
                onClick={() => process(DetectType.Faces)}
                disabled={running === DetectType.Faces}
                variant={'contained'}
                startIcon={<ImageSearchIcon/>}
            >
                Detect faces
            </Button>
        </IntegrationPanelContent>}
        {labels && <div>
            <List
                component="div"
                disablePadding
            >
                <ListSubheader>Labels</ListSubheader>
                {labels.Labels.map(l => {
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
        </div>}
        {texts && <div>
            <List
                component="div"
                disablePadding
            >
                <ListSubheader>Text</ListSubheader>
                {texts.TextDetections.length === 0 && <ListItem>
                    <ListItemText>No text detected</ListItemText>
                </ListItem>}
                {texts.TextDetections.map(l => {
                    return <ListItemButton
                        key={l.Id}
                    >
                        <ListItemText>
                            {l.DetectedText} <small>({Math.round(l.Confidence * 100) / 100}%)</small>
                        </ListItemText>
                    </ListItemButton>
                })}
            </List>
        </div>}
        {faces && <div>
            <List
                component="div"
                disablePadding
            >
                <ListSubheader>Faces</ListSubheader>
                {faces.FaceDetails.length === 0 && <ListItem>
                    <ListItemText>No face detected</ListItemText>
                </ListItem>}
                {faces.FaceDetails.map((l, i) => {
                    return <ListItemButton
                        key={i}
                    >
                        <ListItemText>
                            Face #{i + 1} <small>({Math.round(l.Confidence * 100) / 100}%)</small>
                        </ListItemText>
                    </ListItemButton>
                })}
            </List>
        </div>}
    </>
}
