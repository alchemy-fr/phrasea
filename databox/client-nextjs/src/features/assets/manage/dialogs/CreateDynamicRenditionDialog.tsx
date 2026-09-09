'use client';

import {useMemo, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import type {Asset, AssetRendition, RenditionDefinition} from '@/types/api';
import type {ModalProps} from '@/components/modals/ModalProvider';
import {
    Dialog,
    DialogBody,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {Button} from '@/components/ui/button';
import {FormRow, Input} from '@/components/ui/input';
import {SimpleSelect} from '@/components/ui/select';
import {Checkbox, LabeledControl, Slider} from '@/components/ui/controls';
import {postRendition} from '@/lib/api/misc';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {clamp} from '@/lib/utils/misc';

type Ratio = 'original' | '1:1' | '4:3' | '3:4' | '16:9' | 'custom';
const ratios: Record<Exclude<Ratio, 'original' | 'custom'>, number> = {
    '1:1': 1,
    '4:3': 4 / 3,
    '3:4': 3 / 4,
    '16:9': 16 / 9,
};

/**
 * Builds a custom rendition from a source rendition: interactive crop
 * (aspect ratio + zoom + pan), max dimensions, output format, grayscale and
 * metadata writing. Produces a rendition-factory build definition.
 */
export function CreateDynamicRenditionDialog({
    open,
    onOpenChange,
    asset,
    renditions,
    definitions,
    onCreated,
}: ModalProps & {
    asset: Asset;
    renditions: AssetRendition[];
    definitions: RenditionDefinition[];
    onCreated?: () => void;
}) {
    const {t} = useTranslation();
    const sources = renditions.filter(
        r => r.file?.url && getFileKind(r.file.type) === FileKind.Image
    );
    const [sourceId, setSourceId] = useState(sources[0]?.id);
    const [definitionId, setDefinitionId] = useState(
        definitions.find(d => d.substitutable)?.id
    );
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
    const [loading, setLoading] = useState(false);
    const [natural, setNatural] = useState<{w: number; h: number}>();
    const drag = useRef<{x: number; y: number; ox: number; oy: number} | null>(
        null
    );

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

    const buildDefinition = (): string => {
        const lines: string[] = ['transformations:'];
        if (crop && natural && (ratio !== 'original' || zoom !== 1)) {
            lines.push(
                '  - module: image_crop',
                `    options: {x: ${Math.round(crop.x * natural.w)}, y: ${Math.round(crop.y * natural.h)}, width: ${Math.round(crop.w * natural.w)}, height: ${Math.round(crop.h * natural.h)}}`
            );
        }
        if (maxWidth || maxHeight) {
            lines.push(
                '  - module: image_resize',
                `    options: {width: ${maxWidth || 'null'}, height: ${maxHeight || 'null'}, mode: inset}`
            );
        }
        if (grayscale) {
            lines.push('  - module: image_grayscale');
        }
        if (format !== 'same') {
            lines.push(
                '  - module: image_convert',
                `    options: {format: ${format}}`
            );
        }

        return lines.length > 1 ? lines.join('\n') : 'transformations: []';
    };

    const submit = async () => {
        if (!source || !definitionId) {
            return;
        }
        setLoading(true);
        try {
            await postRendition({
                assetId: asset.id,
                definitionId,
                name: name || undefined,
                sourceRenditionId: source.id,
                buildDefinition: buildDefinition(),
                writeMetadata,
                substituted: true,
                force: true,
            });
            toast.success(t('rendition.created', 'Rendition creation started'));
            onCreated?.();
            onOpenChange(false);
        } catch (e: any) {
            toast.error(e?.message);
        } finally {
            setLoading(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent size="lg">
                <DialogHeader>
                    <DialogTitle>
                        {t(
                            'rendition.create_custom',
                            'Create custom rendition'
                        )}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'rendition.create_custom_help',
                            'Crop, resize and convert a source rendition into a new one.'
                        )}
                    </DialogDescription>
                </DialogHeader>
                <DialogBody>
                    <div className="grid gap-6 md:grid-cols-2">
                        <div className="space-y-3">
                            <FormRow label={t('rendition.source', 'Source')}>
                                <SimpleSelect
                                    value={sourceId}
                                    onValueChange={setSourceId}
                                    options={sources.map(s => ({
                                        value: s.id,
                                        label: s.displayName ?? s.name,
                                    }))}
                                />
                            </FormRow>
                            {source?.file?.url ? (
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
                                                    (e.clientX -
                                                        drag.current.x) /
                                                        rect.width,
                                                0,
                                                1
                                            ),
                                            y: clamp(
                                                drag.current.oy +
                                                    (e.clientY -
                                                        drag.current.y) /
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
                                                h: e.currentTarget
                                                    .naturalHeight,
                                            })
                                        }
                                    />
                                    {crop && natural ? (
                                        <CropOverlay
                                            crop={crop}
                                            natural={natural}
                                        />
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
                            <FormRow
                                label={t(
                                    'rendition.definition',
                                    'Rendition definition'
                                )}
                            >
                                <SimpleSelect
                                    value={definitionId}
                                    onValueChange={setDefinitionId}
                                    options={definitions
                                        .filter(d => d.substitutable)
                                        .map(d => ({
                                            value: d.id,
                                            label: d.displayName ?? d.name,
                                        }))}
                                />
                            </FormRow>
                            <FormRow label={t('common.name', 'Name')}>
                                <Input
                                    value={name}
                                    onChange={e => setName(e.target.value)}
                                    placeholder={t(
                                        'common.optional',
                                        'Optional'
                                    )}
                                />
                            </FormRow>
                            <FormRow
                                label={t('rendition.ratio', 'Aspect ratio')}
                            >
                                <div className="flex gap-2">
                                    <SimpleSelect
                                        value={ratio}
                                        onValueChange={v =>
                                            setRatio(v as Ratio)
                                        }
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
                                <FormRow
                                    label={t(
                                        'rendition.max_width',
                                        'Max width'
                                    )}
                                >
                                    <Input
                                        type="number"
                                        value={maxWidth}
                                        onChange={e =>
                                            setMaxWidth(e.target.value)
                                        }
                                    />
                                </FormRow>
                                <FormRow
                                    label={t(
                                        'rendition.max_height',
                                        'Max height'
                                    )}
                                >
                                    <Input
                                        type="number"
                                        value={maxHeight}
                                        onChange={e =>
                                            setMaxHeight(e.target.value)
                                        }
                                    />
                                </FormRow>
                            </div>
                            <FormRow label={t('rendition.format', 'Format')}>
                                <SimpleSelect
                                    value={format}
                                    onValueChange={v =>
                                        setFormat(v as typeof format)
                                    }
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
                                label={t(
                                    'rendition.grayscale',
                                    'Black & white'
                                )}
                            >
                                <Checkbox
                                    checked={grayscale}
                                    onCheckedChange={v =>
                                        setGrayscale(v === true)
                                    }
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
                                    onCheckedChange={v =>
                                        setWriteMetadata(v === true)
                                    }
                                />
                            </LabeledControl>
                            <pre className="rounded bg-muted p-2 font-mono text-[11px] text-muted-foreground">
                                {buildDefinition()}
                            </pre>
                        </div>
                    </div>
                </DialogBody>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        {t('common.cancel', 'Cancel')}
                    </Button>
                    <Button
                        onClick={submit}
                        disabled={!source || !definitionId}
                        loading={loading}
                    >
                        {t('common.create', 'Create')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
