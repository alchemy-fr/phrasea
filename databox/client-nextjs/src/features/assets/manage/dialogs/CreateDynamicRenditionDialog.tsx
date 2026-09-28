'use client';

import {useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {ApiFile, Asset, AssetRendition} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {FormDialog} from '@/components/modals/FormDialog';
import {FormRow, Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {Checkbox, LabeledControl, Slider} from '@/components/ui/controls';
import {postRendition} from '@/lib/api/misc';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {clamp} from '@/lib/utils/misc';
import {useDirtyState} from '@/lib/navigation/unsavedChanges';

type Ratio = 'original' | '1:1' | '4:3' | '3:4' | '16:9' | 'custom';
const sourceFileKey = 'source';

const ratios: Record<Exclude<Ratio, 'original' | 'custom'>, number> = {
    '1:1': 1,
    '4:3': 4 / 3,
    '3:4': 3 / 4,
    '16:9': 16 / 9,
};

/**
 * Builds a custom rendition from a source rendition: interactive crop
 * (aspect ratio + zoom + pan), max dimensions, output format, grayscale and
 * metadata writing. Produces a rendition-factory build definition: the
 * rendition is not bound to a rendition definition.
 */
export function CreateDynamicRenditionDialog({
    open,
    onOpenChange,
    resolve,
    asset,
    renditions,
    onCreated,
}: ModalProps<boolean> & {
    asset: Asset;
    renditions: AssetRendition[];
    onCreated?: () => void;
}) {
    const {t} = useTranslation();
    // The generated build definition only covers the "image" family
    const isImage = (file?: ApiFile): file is ApiFile =>
        !!file?.url && getFileKind(file.type) === FileKind.Image;
    const sources: {id: string; label: string; file: ApiFile}[] = [
        ...(isImage(asset.source)
            ? [
                  {
                      id: sourceFileKey,
                      label: t('rendition.source_file', 'Source file'),
                      file: asset.source,
                  },
              ]
            : []),
        ...renditions
            .filter(r => r.ready && isImage(r.file))
            .map(r => ({
                id: r.id,
                label: r.displayName ?? r.name,
                file: r.file!,
            })),
    ];
    const [sourceId, setSourceId] = useState(sources[0]?.id);
    const [name, setName] = useState('');
    const [ratio, setRatio] = useState<Ratio>('original');
    const [customRatio, setCustomRatio] = useState('1.5');
    const [zoom, setZoom] = useState(1);
    const [offset, setOffset] = useState({x: 0.5, y: 0.5});
    const [maxWidth, setMaxWidth] = useState('');
    const [maxHeight, setMaxHeight] = useState('');
    const [format, setFormat] = useState<'same' | 'jpeg' | 'png' | 'webp'>(
        'same'
    );
    const [grayscale, setGrayscale] = useState(false);
    const [writeMetadata, setWriteMetadata] = useState(false);
    const [natural, setNatural] = useState<{w: number; h: number}>();
    const drag = useRef<{x: number; y: number; ox: number; oy: number} | null>(
        null
    );

    const {dirty} = useDirtyState({
        sourceId,
        name,
        ratio,
        customRatio,
        zoom,
        offset,
        maxWidth,
        maxHeight,
        format,
        grayscale,
        writeMetadata,
    });

    const source = sources.find(s => s.id === sourceId);
    const aspect =
        ratio === 'original'
            ? natural
                ? natural.w / natural.h
                : 1
            : ratio === 'custom'
              ? parseFloat(customRatio) || 1
              : ratios[ratio];

    // Crop rectangle (relative to the source image) derived from ratio / zoom / offset
    const crop = useMemo(() => {
        if (!natural) {
            return undefined;
        }
        const imgAspect = natural.w / natural.h;
        let w: number;
        let h: number;
        if (aspect >= imgAspect) {
            w = 1 / zoom;
            h = imgAspect / aspect / zoom;
        } else {
            h = 1 / zoom;
            w = aspect / imgAspect / zoom;
        }
        const x = clamp(offset.x - w / 2, 0, 1 - w);
        const y = clamp(offset.y - h / 2, 0, 1 - h);

        return {x, y, w, h};
    }, [natural, aspect, zoom, offset]);

    // rendition-factory definition (image family, Imagine filters). JSON is
    // a subset of YAML: the API parses it as a rendition build definition.
    const buildDefinition = (): string => {
        const filters: Record<string, unknown> = {};
        if (crop && natural && (ratio !== 'original' || zoom !== 1)) {
            filters.crop = {
                start: [
                    Math.round(crop.x * natural.w),
                    Math.round(crop.y * natural.h),
                ],
                size: [
                    Math.round(crop.w * natural.w),
                    Math.round(crop.h * natural.h),
                ],
            };
        }
        const w = parseInt(maxWidth) || 0;
        const h = parseInt(maxHeight) || 0;
        if (w > 0 && h > 0) {
            filters.thumbnail = {size: [w, h], mode: 'inset'};
        } else if (w > 0) {
            filters.relative_resize = {widen: w};
        } else if (h > 0) {
            filters.relative_resize = {heighten: h};
        }
        if (grayscale) {
            filters.grayscale = null;
        }
        const options: Record<string, unknown> = {filters};
        if (format !== 'same') {
            options.format = format;
        }

        return JSON.stringify(
            {image: {transformations: [{module: 'imagine', options}]}},
            null,
            2
        );
    };

    const submit = async () => {
        await postRendition({
            assetId: asset.id,
            name: name.trim(),
            sourceRenditionId:
                source!.id === sourceFileKey ? undefined : source!.id,
            buildDefinition: buildDefinition(),
            writeMetadata,
        });
        toast.success(t('rendition.created', 'Rendition creation started'));
        onCreated?.();
        resolve?.(true);
    };

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('rendition.create_custom', 'Create custom rendition')}
            description={t(
                'rendition.create_custom_help',
                'Crop, resize and convert a source rendition into a new one.'
            )}
            submitLabel={t('common.create', 'Create')}
            canSubmit={!!source && !!name.trim()}
            dirty={dirty}
            onSubmit={submit}
        >
            <div className="grid gap-6 md:grid-cols-2">
                <div className="space-y-3">
                    <FormRow label={t('rendition.source', 'Source')}>
                        <SimpleSelect
                            value={sourceId}
                            onValueChange={setSourceId}
                            options={sources.map(s => ({
                                value: s.id,
                                label: s.label,
                            }))}
                        />
                    </FormRow>
                    {source ? (
                        <div
                            className="relative aspect-square w-full cursor-move overflow-hidden rounded-md bg-media-bg select-none"
                            onMouseDown={e =>
                                (drag.current = {
                                    x: e.clientX,
                                    y: e.clientY,
                                    ox: offset.x,
                                    oy: offset.y,
                                })
                            }
                            onMouseMove={e => {
                                if (!drag.current) {
                                    return;
                                }
                                const rect =
                                    e.currentTarget.getBoundingClientRect();
                                setOffset({
                                    x: clamp(
                                        drag.current.ox +
                                            (e.clientX - drag.current.x) /
                                                rect.width,
                                        0,
                                        1
                                    ),
                                    y: clamp(
                                        drag.current.oy +
                                            (e.clientY - drag.current.y) /
                                                rect.height,
                                        0,
                                        1
                                    ),
                                });
                            }}
                            onMouseUp={() => (drag.current = null)}
                            onMouseLeave={() => (drag.current = null)}
                        >
                            {/* eslint-disable-next-line @next/next/no-img-element */}
                            <img
                                src={source.file.url}
                                alt=""
                                className="absolute inset-0 size-full object-contain"
                                style={{
                                    filter: grayscale
                                        ? 'grayscale(1)'
                                        : undefined,
                                }}
                                draggable={false}
                                onLoad={e =>
                                    setNatural({
                                        w: e.currentTarget.naturalWidth,
                                        h: e.currentTarget.naturalHeight,
                                    })
                                }
                            />
                            {crop && natural ? (
                                <CropOverlay crop={crop} natural={natural} />
                            ) : null}
                        </div>
                    ) : null}
                    <FormRow label={t('rendition.zoom', 'Zoom')}>
                        <Slider
                            min={1}
                            max={5}
                            step={0.05}
                            value={[zoom]}
                            onValueChange={([v]) => setZoom(v)}
                        />
                    </FormRow>
                </div>
                <div className="space-y-3">
                    <FormRow label={t('common.name', 'Name')}>
                        <Input
                            value={name}
                            onChange={e => setName(e.target.value)}
                            required
                        />
                    </FormRow>
                    <FormRow label={t('rendition.ratio', 'Aspect ratio')}>
                        <div className="flex gap-2">
                            <SimpleSelect
                                value={ratio}
                                onValueChange={v => setRatio(v as Ratio)}
                                options={[
                                    {
                                        value: 'original',
                                        label: t(
                                            'rendition.ratio_original',
                                            'Original'
                                        ),
                                    },
                                    {value: '1:1', label: '1:1'},
                                    {value: '4:3', label: '4:3'},
                                    {value: '3:4', label: '3:4'},
                                    {value: '16:9', label: '16:9'},
                                    {
                                        value: 'custom',
                                        label: t(
                                            'rendition.ratio_custom',
                                            'Custom'
                                        ),
                                    },
                                ]}
                            />
                            {ratio === 'custom' ? (
                                <Input
                                    className="w-24"
                                    value={customRatio}
                                    onChange={e =>
                                        setCustomRatio(e.target.value)
                                    }
                                    placeholder="1.5"
                                />
                            ) : null}
                        </div>
                    </FormRow>
                    <div className="grid grid-cols-2 gap-3">
                        <FormRow label={t('rendition.max_width', 'Max width')}>
                            <Input
                                type="number"
                                value={maxWidth}
                                onChange={e => setMaxWidth(e.target.value)}
                            />
                        </FormRow>
                        <FormRow
                            label={t('rendition.max_height', 'Max height')}
                        >
                            <Input
                                type="number"
                                value={maxHeight}
                                onChange={e => setMaxHeight(e.target.value)}
                            />
                        </FormRow>
                    </div>
                    <FormRow label={t('rendition.format', 'Format')}>
                        <SimpleSelect
                            value={format}
                            onValueChange={v => setFormat(v as typeof format)}
                            options={[
                                {
                                    value: 'same',
                                    label: t(
                                        'rendition.format_same',
                                        'Same as source'
                                    ),
                                },
                                {value: 'jpeg', label: 'JPEG'},
                                {value: 'png', label: 'PNG'},
                                {value: 'webp', label: 'WebP'},
                            ]}
                        />
                    </FormRow>
                    <LabeledControl
                        label={t('rendition.grayscale', 'Black & white')}
                    >
                        <Checkbox
                            checked={grayscale}
                            onCheckedChange={v => setGrayscale(v === true)}
                        />
                    </LabeledControl>
                    <LabeledControl
                        label={t(
                            'rendition.write_metadata',
                            'Write attributes as file metadata'
                        )}
                    >
                        <Checkbox
                            checked={writeMetadata}
                            onCheckedChange={v => setWriteMetadata(v === true)}
                        />
                    </LabeledControl>
                    <pre className="rounded bg-muted p-2 font-mono text-[11px] text-muted-foreground">
                        {buildDefinition()}
                    </pre>
                </div>
            </div>
        </FormDialog>
    );
}

function CropOverlay({
    crop,
    natural,
}: {
    crop: {x: number; y: number; w: number; h: number};
    natural: {w: number; h: number};
}) {
    // The image is object-contain inside a square: compute its rendered box
    const imgAspect = natural.w / natural.h;
    const box =
        imgAspect >= 1
            ? {
                  left: 0,
                  top: (1 - 1 / imgAspect) / 2,
                  width: 1,
                  height: 1 / imgAspect,
              }
            : {left: (1 - imgAspect) / 2, top: 0, width: imgAspect, height: 1};
    const style = {
        left: `${(box.left + crop.x * box.width) * 100}%`,
        top: `${(box.top + crop.y * box.height) * 100}%`,
        width: `${crop.w * box.width * 100}%`,
        height: `${crop.h * box.height * 100}%`,
    };

    return (
        <>
            <div className="pointer-events-none absolute inset-0 bg-black/40" />
            <div
                className="pointer-events-none absolute border-2 border-primary shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"
                style={style}
            />
        </>
    );
}
