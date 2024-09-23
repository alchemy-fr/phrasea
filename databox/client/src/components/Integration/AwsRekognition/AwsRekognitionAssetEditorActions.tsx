import {useEffect, useState} from 'react';
import {
    Button,
    List,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
    Tooltip,
} from '@mui/material';
import {runIntegrationAction} from '../../../api/integrations';
import {IntegrationOverlayCommonProps} from '../../Media/Asset/AssetView';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ImageSearchIcon from '@mui/icons-material/ImageSearch';
import {WorkspaceIntegration} from '../../../types';
import IntegrationPanelContent from '../Common/IntegrationPanelContent';
import {
    DetectType,
    FaceDetail,
    FacesData,
    ImageLabel,
    LabelsData,
    TextDetection,
    TextsData,
} from './types';
import FaceDetailTooltip from './FaceDetailTooltip';
import ValueConfidence from './ValueConfidence';
import {AssetIntegrationActionsProps} from '../types.ts';
import {useTranslation} from 'react-i18next';

function ImageOverlay({
    labels,
    texts,
    faces,
}: {
    labels: ImageLabel[] | undefined;
    texts: TextDetection[] | undefined;
    faces: FaceDetail[] | undefined;
} & IntegrationOverlayCommonProps) {
    const {t} = useTranslation();

    return (
        <div>
            {labels &&
                labels.map((il, j) => {
                    return (
                        <>
                            {il.Instances.map((i, k) => {
                                const box = i.BoundingBox;

                                const percent = (x: number) => `${x * 100}%`;

                                return (
                                    <Tooltip
                                        title={
                                            <>
                                                {il.Name}{' '}
                                                <small>
                                                    (
                                                    <ValueConfidence
                                                        confidence={
                                                            il.Confidence
                                                        }
                                                    />
                                                    )
                                                </small>
                                            </>
                                        }
                                        arrow
                                    >
                                        <div
                                            key={`${j}-${k}`}
                                            style={{
                                                position: 'absolute',
                                                top: percent(box.Top),
                                                left: percent(box.Left),
                                                width: percent(box.Width),
                                                height: percent(box.Height),
                                                boxShadow: `0 0 3px blue, 0 0 3px inset blue`,
                                            }}
                                        ></div>
                                    </Tooltip>
                                );
                            })}
                        </>
                    );
                })}
            {texts &&
                texts.map((i, k) => {
                    const box = i.Geometry.BoundingBox;

                    const percent = (x: number) => `${x * 100}%`;

                    return (
                        <div
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
                    );
                })}
            {faces &&
                faces.map((i, k) => {
                    const box = i.BoundingBox;
                    const percent = (x: number) => `${x * 100}%`;

                    return (
                        <Tooltip
                            title={
                                <FaceDetailTooltip
                                    detail={i}
                                    title={t('aws_rekognition.actions.face_n', {
                                        defaultValue: `Face #{{n}}`,
                                        n: k + 1,
                                    })}
                                />
                            }
                            arrow
                        >
                            <div
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
                        </Tooltip>
                    );
                })}
        </div>
    );
}

function parseData<T>(
    integration: WorkspaceIntegration,
    key: string
): T | undefined {
    const value = integration.data.find(d => d.name === key);

    if (!value) {
        return;
    }

    return JSON.parse(value.value) as T;
}

type Props = {} & AssetIntegrationActionsProps;

type ApiCategory = {
    enabled: boolean;
};

export default function AwsRekognitionAssetEditorActions({
    file,
    integration,
    setIntegrationOverlay,
    enableInc,
}: Props) {
    const {t} = useTranslation();
    const [running, setRunning] = useState<DetectType | undefined>();
    const [labels, setLabels] = useState<LabelsData | undefined>();
    const [texts, setTexts] = useState<TextsData | undefined>();
    const [faces, setFaces] = useState<FacesData | undefined>();

    const process = async (category: DetectType) => {
        setRunning(category);
        try {
            const res = await runIntegrationAction('analyze', integration.id, {
                fileId: file.id,
                category,
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
        const instances =
            labels?.Labels.filter(l => l.Instances.length > 0) ?? [];

        if (enableInc && (instances.length > 0 || texts || faces)) {
            setIntegrationOverlay(ImageOverlay, {
                labels: instances,
                texts: texts?.TextDetections,
                faces: faces?.FaceDetails,
            });
        }
    }, [enableInc, labels, texts, faces]);

    const config = integration.config as {
        labels: ApiCategory;
        texts: ApiCategory;
        faces: ApiCategory;
    };

    return (
        <>
            {config.labels.enabled && !labels && (
                <IntegrationPanelContent>
                    <Button
                        onClick={() => process(DetectType.Labels)}
                        disabled={running === DetectType.Labels}
                        variant={'contained'}
                        startIcon={<ImageSearchIcon />}
                    >
                        {t(
                            'aws_rekognition.actions.detect_image_labels',
                            `Detect image labels`
                        )}
                    </Button>
                </IntegrationPanelContent>
            )}
            {config.texts.enabled && !texts && (
                <IntegrationPanelContent>
                    <Button
                        onClick={() => process(DetectType.Texts)}
                        disabled={running === DetectType.Texts}
                        variant={'contained'}
                        startIcon={<ImageSearchIcon />}
                    >
                        {t(
                            'aws_rekognition.actions.detect_texts',
                            `Detect texts`
                        )}
                    </Button>
                </IntegrationPanelContent>
            )}
            {config.faces.enabled && !faces && (
                <IntegrationPanelContent>
                    <Button
                        onClick={() => process(DetectType.Faces)}
                        disabled={running === DetectType.Faces}
                        variant={'contained'}
                        startIcon={<ImageSearchIcon />}
                    >
                        {t(
                            'aws_rekognition.actions.detect_faces',
                            `Detect faces`
                        )}
                    </Button>
                </IntegrationPanelContent>
            )}
            {labels && (
                <div>
                    <List component="div" disablePadding>
                        <ListSubheader>
                            {t('aws_rekognition.actions.labels', `Labels`)}
                        </ListSubheader>
                        {labels.Labels.map(l => {
                            return (
                                <ListItemButton key={l.Name}>
                                    <ListItemText>
                                        {l.Name}{' '}
                                        <small>
                                            (
                                            <ValueConfidence
                                                confidence={l.Confidence}
                                            />
                                            )
                                        </small>
                                    </ListItemText>
                                    {l.Instances.length > 0 && (
                                        <ListItemIcon>
                                            <VisibilityIcon />
                                        </ListItemIcon>
                                    )}
                                </ListItemButton>
                            );
                        })}
                    </List>
                </div>
            )}
            {texts && (
                <div>
                    <List component="div" disablePadding>
                        <ListSubheader>
                            {t('aws_rekognition.actions.text', `Text`)}
                        </ListSubheader>
                        {texts.TextDetections.length === 0 && (
                            <ListItem>
                                <ListItemText>
                                    {t(
                                        'aws_rekognition.actions.no_text_detected',
                                        `No text detected`
                                    )}
                                </ListItemText>
                            </ListItem>
                        )}
                        {texts.TextDetections.map(l => {
                            return (
                                <ListItemButton key={l.Id}>
                                    <ListItemText>
                                        {l.DetectedText}{' '}
                                        <small>
                                            (
                                            <ValueConfidence
                                                confidence={l.Confidence}
                                            />
                                            )
                                        </small>
                                    </ListItemText>
                                </ListItemButton>
                            );
                        })}
                    </List>
                </div>
            )}
            {faces && (
                <div>
                    <List component="div" disablePadding>
                        <ListSubheader>
                            {t('aws_rekognition.actions.faces', `Faces`)}
                        </ListSubheader>
                        {faces.FaceDetails.length === 0 && (
                            <ListItem>
                                <ListItemText>
                                    {t(
                                        'aws_rekognition.actions.no_face_detected',
                                        `No face detected`
                                    )}
                                </ListItemText>
                            </ListItem>
                        )}
                        {faces.FaceDetails.map((l, i) => {
                            return (
                                <ListItemButton key={i}>
                                    <ListItemText>
                                        {t('aws_rekognition.actions.face_n', {
                                            defaultValue: `Face #{{n}}`,
                                            n: i + 1,
                                        })}{' '}
                                        <small>
                                            (
                                            <ValueConfidence
                                                confidence={l.Confidence}
                                            />
                                            )
                                        </small>
                                    </ListItemText>
                                </ListItemButton>
                            );
                        })}
                    </List>
                </div>
            )}
        </>
    );
}
