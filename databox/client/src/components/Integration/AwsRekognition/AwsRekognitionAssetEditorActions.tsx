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
import {ObjectType, runIntegrationAction} from '../../../api/integrations';
import {IntegrationOverlayCommonProps} from '../../Media/Asset/View/AssetView.tsx';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ImageSearchIcon from '@mui/icons-material/ImageSearch';
import {IntegrationData} from '../../../types';
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
import {AssetIntegrationActionsProps, Integration} from '../types.ts';
import {useTranslation} from 'react-i18next';
import {useChannelRegistration} from '../../../lib/pusher.ts';
import {useIntegrationData} from '../useIntegrationData.ts';

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

function parseData<T>(data: IntegrationData[], key: string): T | undefined {
    const value = data.find(d => d.name === key);

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
    const [running, setRunning] = useState<DetectType[]>([]);
    const [labels, setLabels] = useState<LabelsData | undefined>();
    const [texts, setTexts] = useState<TextsData | undefined>();
    const [faces, setFaces] = useState<FacesData | undefined>();

    const {data, load: loadData} = useIntegrationData({
        objectType: ObjectType.File,
        objectId: file.id,
        integrationId: integration.id,
        defaultData: integration.data,
    });

    const process = async (category: DetectType) => {
        setRunning(p => p.concat([category]));
        try {
            await runIntegrationAction('analyze', integration.id, {
                fileId: file.id,
                category,
            });
        } catch (e) {
            setRunning(p => p.filter(c => c !== category));
            throw e;
        }
    };

    useEffect(() => {
        const allData = data.pages.flat();
        setLabels(parseData(allData, 'labels'));
        setTexts(parseData(allData, 'texts'));
        setFaces(parseData(allData, 'faces'));
    }, [data]);

    useChannelRegistration(
        `file-${file.id}`,
        `integration:${Integration.AwsRekognition}`,
        () => {
            setRunning([]);
            loadData();
        }
    );

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
                        disabled={running.includes(DetectType.Labels)}
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
                        disabled={running.includes(DetectType.Texts)}
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
                        disabled={running.includes(DetectType.Faces)}
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
