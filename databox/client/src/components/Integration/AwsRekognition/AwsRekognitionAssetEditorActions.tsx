import {useCallback, useEffect, useMemo, useState} from 'react';
import {
    Button,
    List,
    ListItem,
    ListItemButton,
    ListItemIcon,
    ListItemText,
    ListSubheader,
} from '@mui/material';
import {ObjectType, runIntegrationAction} from '../../../api/integrations';
import VisibilityIcon from '@mui/icons-material/Visibility';
import ImageSearchIcon from '@mui/icons-material/ImageSearch';
import {IntegrationData} from '../../../types';
import IntegrationPanelContent from '../Common/IntegrationPanelContent';
import {DetectType, FacesData, LabelsData, TextsData} from './types';
import ValueConfidence from './ValueConfidence';
import {AssetIntegrationActionsProps, Integration} from '../types.ts';
import {useTranslation} from 'react-i18next';
import {useChannelRegistration} from '../../../lib/pusher.ts';
import {useIntegrationData} from '../useIntegrationData.ts';
import {
    AnnotationType,
    AssetAnnotation,
} from '../../Media/Asset/Annotations/annotationTypes.ts';

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

type Categories = {
    titleLabel: string;
    noneLabel: string;
    detectLabel: string;
    type: DetectType;
    hasData: boolean;
}[];

type NormalizedAnnotationItem = {
    id: string;
    type: DetectType;
    title: string;
    confidence: number;
    instances: AssetAnnotation[];
};

export default function AwsRekognitionAssetEditorActions({
    file,
    integration,
    assetAnnotationsRef,
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

    const annotations = useMemo<NormalizedAnnotationItem[]>(() => {
        const instances =
            labels?.Labels.filter(l => l.Instances.length > 0) ?? [];

        if (enableInc && (instances.length > 0 || texts || faces)) {
            const annotations: NormalizedAnnotationItem[] = [
                ...instances.map((i, index) => {
                    const id = `label-${index}`;
                    return {
                        id,
                        title: i.Name,
                        type: DetectType.Labels,
                        confidence: i.Confidence,
                        instances: i.Instances.map(instance => {
                            const box = instance.BoundingBox;

                            return {
                                id: `${id}-${instance.BoundingBox.Left}-${instance.BoundingBox.Top}`,
                                type: AnnotationType.Rect,
                                c: 'blue',
                                x: box.Left,
                                y: box.Top,
                                w: box.Width,
                                h: box.Height,
                                name: i.Name,
                            };
                        }),
                    };
                }),

                ...(texts?.TextDetections.map((t, index) => {
                    const box = t.Geometry.BoundingBox;
                    const id = `text-${index}`;

                    return {
                        id,
                        title: t.DetectedText,
                        type: DetectType.Texts,
                        confidence: t.Confidence,
                        instances: [
                            {
                                id: `${id}-0`,
                                type: AnnotationType.Rect,
                                x: box.Left,
                                y: box.Top,
                                w: box.Width,
                                h: box.Height,
                                name: t.DetectedText,
                                c: 'green',
                            },
                        ],
                    };
                }) ?? []),

                ...(faces?.FaceDetails.map((f, i) => {
                    const box = f.BoundingBox;
                    const title = t('aws_rekognition.actions.face_n', {
                        defaultValue: `Face #{{n}}`,
                        n: i + 1,
                    });

                    const id = `face-${i}`;
                    return {
                        id,
                        type: DetectType.Faces,
                        confidence: f.Confidence,
                        title,
                        instances: [
                            {
                                id: `${id}-0`,
                                type: AnnotationType.Rect,
                                c: 'red',
                                x: box.Left,
                                y: box.Top,
                                w: box.Width,
                                h: box.Height,
                                name: title,
                            },
                        ],
                    };
                }) ?? []),
            ];

            return annotations;
        }

        return [];
    }, [enableInc, labels, texts, faces]);

    useEffect(() => {
        assetAnnotationsRef?.current?.replaceAnnotations(
            annotations.map(a => a.instances).flat()
        );
    }, [enableInc, labels, texts, faces]);

    const config = integration.config as {
        labels: ApiCategory;
        texts: ApiCategory;
        faces: ApiCategory;
    };

    const categories = useMemo<Categories>(
        () => [
            {
                type: DetectType.Labels,
                hasData: !!labels,
                titleLabel: t('aws_rekognition.actions.labels', `Labels`),
                noneLabel: t(
                    'aws_rekognition.actions.no_label_detected',
                    `No label detected`
                ),
                detectLabel: t(
                    'aws_rekognition.actions.detect_image_labels',
                    `Detect image labels`
                ),
            },
            {
                type: DetectType.Texts,
                hasData: !!texts,
                titleLabel: t('aws_rekognition.actions.texts', `Texts`),
                noneLabel: t(
                    'aws_rekognition.actions.no_text_detected',
                    `No text detected`
                ),
                detectLabel: t(
                    'aws_rekognition.actions.detect_texts',
                    `Detect texts`
                ),
            },
            {
                type: DetectType.Faces,
                hasData: !!faces,
                titleLabel: t('aws_rekognition.actions.faces', `Faces`),
                noneLabel: t(
                    'aws_rekognition.actions.no_face_detected',
                    `No face detected`
                ),
                detectLabel: t(
                    'aws_rekognition.actions.detect_faces',
                    `Detect faces`
                ),
            },
        ],
        [t, labels, texts, faces]
    );

    const selectItem = useCallback(
        (item: NormalizedAnnotationItem) => {
            const ar = assetAnnotationsRef?.current;
            if (ar) {
                item.instances.forEach(i => ar.selectAnnotation(i));
                ar.replaceAnnotations(
                    annotations
                        .filter(a => a.type === item.type)
                        .map(a => a.instances as AssetAnnotation[])
                        .flat()
                );
            }
        },
        [assetAnnotationsRef, annotations]
    );

    return (
        <>
            {categories
                .filter(
                    category =>
                        config[category.type].enabled && !category.hasData
                )
                .map(category => {
                    return (
                        <IntegrationPanelContent key={category.type}>
                            <Button
                                onClick={() => process(category.type)}
                                disabled={running.includes(category.type)}
                                variant={'contained'}
                                startIcon={<ImageSearchIcon />}
                            >
                                {category.detectLabel}
                            </Button>
                        </IntegrationPanelContent>
                    );
                })}

            {categories
                .filter(
                    category =>
                        config[category.type].enabled && category.hasData
                )
                .map(category => {
                    const items = annotations.filter(
                        a => a.type === category.type
                    );

                    return (
                        <div key={category.type}>
                            <List component="div" disablePadding>
                                <ListSubheader>
                                    {category.titleLabel}
                                </ListSubheader>
                                {items.length === 0 && (
                                    <ListItem>
                                        <ListItemText>
                                            {category.noneLabel}
                                        </ListItemText>
                                    </ListItem>
                                )}
                                {items.map(l => {
                                    return (
                                        <ListItemButton
                                            key={l.id}
                                            onClick={() => selectItem(l)}
                                        >
                                            <ListItemText>
                                                {l.title}{' '}
                                                <small>
                                                    (
                                                    <ValueConfidence
                                                        confidence={
                                                            l.confidence
                                                        }
                                                    />
                                                    )
                                                </small>
                                            </ListItemText>
                                            {l.instances.length > 0 && (
                                                <ListItemIcon>
                                                    <VisibilityIcon />
                                                </ListItemIcon>
                                            )}
                                        </ListItemButton>
                                    );
                                })}
                            </List>
                        </div>
                    );
                })}
        </>
    );
}
