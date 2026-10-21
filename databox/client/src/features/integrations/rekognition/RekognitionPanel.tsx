'use client';

import {useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {useQuery, useQueryClient} from '@tanstack/react-query';
import {EyeIcon, ScanEyeIcon} from 'lucide-react';
import {toast} from 'sonner';
import type {
    ApiFile,
    Asset,
    AssetAnnotation,
    IntegrationData,
    WorkspaceIntegration,
} from '@/types/api';
import {
    getIntegrationData,
    IntegrationObjectType,
    runIntegrationAction,
} from '@/lib/api/integrations';
import {Button} from '@/components/ui/button';
import {InlineLoader} from '@/components/ui/loader';
import {useChannelEvent} from '@/lib/realtime/RealtimeProvider';
import {cn} from '@/lib/utils/cn';
import {useAnnotations} from '@/features/annotations/AnnotationsProvider';
import {AnnotationType} from '@/features/annotations/types';

export const awsRekognition = 'aws.rekognition';

type DetectType = 'labels' | 'texts' | 'faces';

type BoundingBox = {Left: number; Top: number; Width: number; Height: number};

type LabelsData = {
    Labels: {
        Name: string;
        Confidence: number;
        Instances?: {BoundingBox: BoundingBox}[];
    }[];
};

type TextsData = {
    TextDetections: {
        DetectedText: string;
        Confidence: number;
        Type: 'LINE' | 'WORD';
        Geometry: {BoundingBox: BoundingBox};
    }[];
};

type FacesData = {
    FaceDetails: {Confidence: number; BoundingBox: BoundingBox}[];
};

/** A detected thing, with the boxes drawn on the image for it */
type DetectedItem = {
    id: string;
    type: DetectType;
    title: string;
    confidence: number;
    boxes: AssetAnnotation[];
};

const boxColors: Record<Exclude<DetectType, 'faces'>, string> = {
    labels: '#2563eb',
    texts: '#16a34a',
};

function parseData<T>(items: IntegrationData[], key: string): T | undefined {
    const item = items.find(d => d.name === key);
    if (!item) {
        return;
    }
    try {
        return (
            typeof item.value === 'string' ? JSON.parse(item.value) : item.value
        ) as T;
    } catch {
        return;
    }
}

function toBox(
    id: string,
    type: DetectType,
    name: string,
    box: BoundingBox
): AssetAnnotation {
    const position = {
        x: box.Left,
        y: box.Top,
        w: box.Width,
        h: box.Height,
    };
    // A face is drawn as the faces of the face recognition, by the client
    if (type === 'faces') {
        return {id, type: AnnotationType.Face, name, ...position};
    }

    return {
        id,
        type: AnnotationType.Rect,
        name,
        c: boxColors[type],
        ...position,
    };
}

/**
 * AWS Rekognition panel: runs the detections enabled in the configuration
 * (labels, texts, faces) and lists what was found; the bounding boxes are
 * shown over the image when an item is picked.
 */
export function RekognitionPanel({
    integration,
    asset,
    file,
}: {
    integration: WorkspaceIntegration;
    asset: Asset;
    file: ApiFile;
}) {
    const {t} = useTranslation();
    const queryClient = useQueryClient();
    const annotations = useAnnotations();
    const [running, setRunning] = useState<DetectType[]>([]);
    const queryKey = ['integration-data', integration.id, file.id];

    const data = useQuery({
        queryKey,
        queryFn: ({signal}) =>
            getIntegrationData(
                integration.id,
                {type: IntegrationObjectType.File, id: file.id},
                signal
            ),
    });

    useChannelEvent(
        `file-${file.id}`,
        `integration:${integration.integration}`,
        () => {
            setRunning([]);
            void queryClient.invalidateQueries({queryKey});
        }
    );

    const items = useMemo(() => data.data?.items ?? [], [data.data]);
    const labels = useMemo(
        () => parseData<LabelsData>(items, 'labels'),
        [items]
    );
    const texts = useMemo(() => parseData<TextsData>(items, 'texts'), [items]);
    const faces = useMemo(() => parseData<FacesData>(items, 'faces'), [items]);

    const detected = useMemo<DetectedItem[]>(
        () => [
            ...(labels?.Labels ?? []).map((l, i) => {
                const id = `label-${i}`;

                return {
                    id,
                    type: 'labels' as const,
                    title: l.Name,
                    confidence: l.Confidence,
                    boxes: (l.Instances ?? []).map((instance, j) =>
                        toBox(
                            `${id}-${j}`,
                            'labels',
                            l.Name,
                            instance.BoundingBox
                        )
                    ),
                };
            }),
            ...(texts?.TextDetections ?? [])
                .filter(td => td.Type === 'LINE')
                .map((td, i) => {
                    const id = `text-${i}`;

                    return {
                        id,
                        type: 'texts' as const,
                        title: td.DetectedText,
                        confidence: td.Confidence,
                        boxes: [
                            toBox(
                                `${id}-0`,
                                'texts',
                                td.DetectedText,
                                td.Geometry.BoundingBox
                            ),
                        ],
                    };
                }),
            ...(faces?.FaceDetails ?? []).map((f, i) => {
                const id = `face-${i}`;
                const title = t('rekognition.face_n', 'Face #{{n}}', {
                    n: i + 1,
                });

                return {
                    id,
                    type: 'faces' as const,
                    title,
                    confidence: f.Confidence,
                    boxes: [toBox(`${id}-0`, 'faces', title, f.BoundingBox)],
                };
            }),
        ],
        [labels, texts, faces, t]
    );

    const run = async (category: DetectType) => {
        setRunning(p => [...p, category]);
        try {
            await runIntegrationAction(integration.id, 'analyze', {
                fileId: file.id,
                assetId: asset.id,
                category,
            });
        } catch (e: any) {
            setRunning(p => p.filter(c => c !== category));
            toast.error(e?.message);
        }
    };

    const targetKey = (type: DetectType) => `rekognition-${file.id}-${type}`;
    const show = (type: DetectType, item?: DetectedItem) => {
        annotations?.setTarget({
            key: targetKey(type),
            annotations: detected
                .filter(d => d.type === type)
                .flatMap(d => d.boxes),
            selectedId: item?.boxes[0]?.id,
            label: t('rekognition.title', 'Rekognition'),
        });
    };

    const config = integration.config as Partial<
        Record<DetectType, {enabled?: boolean}>
    >;
    const categories: {
        type: DetectType;
        hasData: boolean;
        title: string;
        none: string;
        detect: string;
    }[] = [
        {
            type: 'labels',
            hasData: !!labels,
            title: t('rekognition.labels', 'Labels'),
            none: t('rekognition.no_label', 'No label detected'),
            detect: t('rekognition.detect_labels', 'Detect labels'),
        },
        {
            type: 'texts',
            hasData: !!texts,
            title: t('rekognition.texts', 'Texts'),
            none: t('rekognition.no_text', 'No text detected'),
            detect: t('rekognition.detect_texts', 'Detect texts'),
        },
        {
            type: 'faces',
            hasData: !!faces,
            title: t('rekognition.faces', 'Faces'),
            none: t('rekognition.no_face', 'No face detected'),
            detect: t('rekognition.detect_faces', 'Detect faces'),
        },
    ];
    const enabled = categories.filter(c => config[c.type]?.enabled);
    const canInteract = integration.capabilities?.interact !== false;
    const canShow = !!annotations?.supported;

    return (
        <div className="space-y-3 text-sm">
            {data.isLoading ? <InlineLoader /> : null}
            {canInteract && enabled.some(c => !c.hasData) ? (
                <div className="flex flex-wrap gap-2">
                    {enabled
                        .filter(c => !c.hasData)
                        .map(c => (
                            <Button
                                key={c.type}
                                size="sm"
                                variant="outline"
                                onClick={() => run(c.type)}
                                loading={running.includes(c.type)}
                            >
                                <ScanEyeIcon /> {c.detect}
                            </Button>
                        ))}
                </div>
            ) : null}
            {enabled
                .filter(c => c.hasData)
                .map(c => {
                    const list = detected.filter(d => d.type === c.type);
                    const active =
                        annotations?.target?.key === targetKey(c.type);
                    const selectedId = active
                        ? annotations?.target?.selectedId
                        : undefined;

                    return (
                        <div key={c.type} data-testid={`rekognition-${c.type}`}>
                            <div className="mb-1 flex items-center gap-2">
                                <span className="text-xs font-medium text-muted-foreground">
                                    {c.title}
                                </span>
                                {canShow && list.some(d => d.boxes.length) ? (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className={cn(
                                            'ml-auto h-6 text-xs',
                                            active && 'text-primary'
                                        )}
                                        onClick={() =>
                                            active
                                                ? annotations?.clearTarget(
                                                      targetKey(c.type)
                                                  )
                                                : show(c.type)
                                        }
                                    >
                                        <EyeIcon />{' '}
                                        {active
                                            ? t(
                                                  'annotation.hide',
                                                  'Hide annotations'
                                              )
                                            : t(
                                                  'rekognition.show_all',
                                                  'Show on the asset'
                                              )}
                                    </Button>
                                ) : null}
                            </div>
                            {list.length === 0 ? (
                                <p className="text-xs text-muted-foreground">
                                    {c.none}
                                </p>
                            ) : (
                                <ul className="max-h-64 space-y-0.5 overflow-y-auto">
                                    {list.map(d => {
                                        const selected =
                                            !!selectedId &&
                                            d.boxes.some(
                                                b => b.id === selectedId
                                            );
                                        const clickable =
                                            canShow && d.boxes.length > 0;

                                        return (
                                            <li key={d.id}>
                                                <button
                                                    type="button"
                                                    disabled={!clickable}
                                                    onClick={() =>
                                                        show(c.type, d)
                                                    }
                                                    className={cn(
                                                        'flex w-full items-center gap-2 rounded px-2 py-1 text-left text-xs',
                                                        clickable &&
                                                            'hover:bg-accent',
                                                        selected && 'bg-accent'
                                                    )}
                                                >
                                                    <span className="min-w-0 flex-1 truncate">
                                                        {d.title}
                                                    </span>
                                                    <span className="font-mono text-muted-foreground">
                                                        {Math.round(
                                                            d.confidence
                                                        )}
                                                        %
                                                    </span>
                                                    {d.boxes.length > 0 ? (
                                                        <EyeIcon className="size-3.5 shrink-0 text-muted-foreground" />
                                                    ) : null}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>
                    );
                })}
            {!data.isLoading && enabled.length === 0 ? (
                <p className="text-xs text-muted-foreground">
                    {t('integrations.no_data', 'No data yet')}
                </p>
            ) : null}
        </div>
    );
}
