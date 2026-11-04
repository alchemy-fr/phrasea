'use client';

import {useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {toast} from 'sonner';
import {PauseIcon, PlayIcon, Trash2Icon} from 'lucide-react';
import type {ApiFile, Asset} from '@/types/api';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {Button} from '@alchemy/next-ui/ui/button';
import {LabeledControl, Slider, Switch} from '@alchemy/next-ui/ui/controls';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {patchBasketAssetClip} from '@/lib/api/misc';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {toastError} from '@alchemy/next-ui/utils/errors';
import {AudioPlayer} from '@/features/assets/player/AudioPlayer';
import {
    type CropRatioName,
    cropRatioNames,
    cropRatios,
} from '@/features/assets/crop/ratios';
import type {ClipCrop, ClipTimeRange} from './types';
import {
    formatClipTime,
    getClipCapabilities,
    normalizeClip,
    parseClipTime,
} from './clip';
import {CropRectEditor, fitCropToRatio} from './CropRectEditor';
import {useClipPlayback} from './useClipPlayback';

type RatioChoice = 'free' | 'original' | CropRatioName;

/**
 * The media the clip is drawn on: the preview when it is of the same kind as
 * the source (lighter), the source otherwise.
 */
function getMediaFile(asset: Asset): ApiFile | undefined {
    const sourceKind = getFileKind(asset.source?.type);
    const preview = asset.preview?.file;
    if (preview?.url && getFileKind(preview.type) === sourceKind) {
        return preview;
    }

    return asset.source?.url ? asset.source : undefined;
}

/**
 * Time range (video, audio) and crop (image, video) of a basket item. The
 * file itself is not cut: the clip is applied when the item is played, and
 * when it is published or exported.
 */
export function BasketClipDialog({
    open,
    onOpenChange,
    resolve,
    asset,
}: ModalProps<boolean> & {asset: Asset}) {
    const {t} = useTranslation();
    const itemId = asset.basketItemId!;
    const initial = asset.basketClip;
    const capabilities = getClipCapabilities(asset.source?.type);
    const media = getMediaFile(asset);
    const mediaKind = getFileKind(media?.type);

    const [range, setRange] = useState<ClipTimeRange | undefined>(
        capabilities.timeRange ? initial?.timeRange : undefined
    );
    const [crop, setCrop] = useState<ClipCrop | undefined>(
        capabilities.crop ? initial?.crop : undefined
    );
    const [cropEnabled, setCropEnabled] = useState(!!crop);
    const [ratio, setRatio] = useState<RatioChoice>('free');
    const [duration, setDuration] = useState<number>();
    const [mediaAspect, setMediaAspect] = useState<number>();
    const [currentTime, setCurrentTime] = useState(0);
    const [playing, setPlaying] = useState(false);
    const [removing, setRemoving] = useState(false);
    const videoRef = useRef<HTMLVideoElement>(null);

    const {dirty} = useDirtyState({range, crop: cropEnabled ? crop : null});

    const ratioValue =
        ratio === 'free'
            ? undefined
            : ratio === 'original'
              ? mediaAspect
              : cropRatios[ratio];

    useClipPlayback(videoRef, range);

    const rangeInvalid = !!range && !(range.e > range.s);
    const clip = normalizeClip(
        {
            timeRange: range,
            crop: cropEnabled ? crop : undefined,
        },
        duration
    );

    const seek = (time: number) => {
        if (videoRef.current) {
            videoRef.current.currentTime = time;
        }
        setCurrentTime(time);
    };

    const setRangeFromSlider = ([s, e]: number[]) => {
        if (range && e !== range.e) {
            // Show the last frame of the range, still within it
            seek(Math.max(s, e - 0.1));
        } else if (s !== range?.s) {
            seek(s);
        }
        setRange({s, e});
    };

    const toggleRange = (enabled: boolean) => {
        if (!enabled) {
            setRange(undefined);

            return;
        }
        const end = duration ?? Math.max(currentTime + 10, 10);
        setRange({s: Math.min(currentTime, end), e: end});
    };

    const toggleCrop = (enabled: boolean) => {
        setCropEnabled(enabled);
        if (enabled && !crop) {
            setCrop(
                ratioValue && mediaAspect
                    ? fitCropToRatio(undefined, ratioValue, mediaAspect)
                    : {x: 0.1, y: 0.1, w: 0.8, h: 0.8}
            );
        }
    };

    const changeRatio = (value: RatioChoice) => {
        setRatio(value);
        const r =
            value === 'free'
                ? undefined
                : value === 'original'
                  ? mediaAspect
                  : cropRatios[value];
        if (r && mediaAspect && cropEnabled) {
            setCrop(fitCropToRatio(crop, r, mediaAspect));
        }
    };

    const togglePlay = () => {
        const el = videoRef.current;
        if (!el) {
            return;
        }
        if (el.paused) {
            el.play()?.catch(() => undefined);
        } else {
            el.pause();
        }
    };

    const submit = async () => {
        await patchBasketAssetClip(itemId, clip);
        toast.success(
            clip
                ? t('basket.clip.saved', 'Extract saved')
                : t('basket.clip.removed', 'Extract removed')
        );
        resolve?.(true);
    };

    const remove = async () => {
        setRemoving(true);
        try {
            await patchBasketAssetClip(itemId, null);
            toast.success(t('basket.clip.removed', 'Extract removed'));
            resolve?.(true);
        } catch (e) {
            toastError(e);
        } finally {
            setRemoving(false);
        }
    };

    const mediaElement =
        media && mediaKind === FileKind.Video ? (
            <video
                ref={videoRef}
                src={media.url}
                className="size-full"
                playsInline
                preload="metadata"
                onLoadedMetadata={e => {
                    const v = e.currentTarget;
                    if (Number.isFinite(v.duration)) {
                        setDuration(v.duration);
                    }
                    if (v.videoWidth > 0 && v.videoHeight > 0) {
                        setMediaAspect(v.videoWidth / v.videoHeight);
                    }
                }}
                onTimeUpdate={e => setCurrentTime(e.currentTarget.currentTime)}
                onPlay={() => setPlaying(true)}
                onPause={() => setPlaying(false)}
            />
        ) : media && mediaKind === FileKind.Image ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
                src={media.url}
                alt={asset.name ?? ''}
                className="size-full"
                draggable={false}
                onLoad={e => {
                    const {naturalWidth, naturalHeight} = e.currentTarget;
                    if (naturalWidth > 0 && naturalHeight > 0) {
                        setMediaAspect(naturalWidth / naturalHeight);
                    }
                }}
            />
        ) : null;

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            size="lg"
            title={t('basket.clip.dialog.title', 'Cut / crop')}
            description={t(
                'basket.clip.dialog.description',
                'The file is not modified: the extract applies to this basket item when it is played, published or exported.'
            )}
            submitLabel={t('common.save', 'Save')}
            canSubmit={!rangeInvalid && !removing}
            dirty={dirty}
            onSubmit={submit}
            footerStart={
                initial ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="text-destructive"
                        disabled={removing}
                        onClick={remove}
                    >
                        <Trash2Icon />{' '}
                        {t('basket.clip.remove', 'Remove extract')}
                    </Button>
                ) : null
            }
            bodyClassName="space-y-4"
        >
            {mediaKind === FileKind.Audio && media?.url ? (
                <AudioPlayer
                    src={media.url}
                    timeRange={range && !rangeInvalid ? range : undefined}
                    onTimeUpdate={setCurrentTime}
                    onDuration={setDuration}
                />
            ) : mediaElement ? (
                <div className="space-y-2">
                    <div className="h-80 overflow-hidden rounded-md bg-media-bg">
                        <CropRectEditor
                            value={crop}
                            onChange={setCrop}
                            ratio={ratioValue}
                            mediaAspect={mediaAspect}
                            enabled={cropEnabled}
                        >
                            {mediaElement}
                        </CropRectEditor>
                    </div>
                    {mediaKind === FileKind.Video ? (
                        <div className="flex items-center gap-3">
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                onClick={togglePlay}
                                aria-label={
                                    playing
                                        ? t('player.pause', 'Pause')
                                        : t('player.play', 'Play')
                                }
                            >
                                {playing ? <PauseIcon /> : <PlayIcon />}
                            </Button>
                            <span className="font-mono text-sm tabular-nums text-muted-foreground">
                                {formatClipTime(currentTime)}
                                {duration !== undefined
                                    ? ` / ${formatClipTime(duration)}`
                                    : null}
                            </span>
                        </div>
                    ) : null}
                </div>
            ) : null}

            {capabilities.timeRange ? (
                <section className="space-y-3">
                    <LabeledControl
                        label={t('basket.clip.time_range', 'Time range')}
                    >
                        <Switch
                            checked={!!range}
                            onCheckedChange={toggleRange}
                        />
                    </LabeledControl>
                    {range ? (
                        <>
                            {duration ? (
                                <Slider
                                    min={0}
                                    max={duration}
                                    step={0.1}
                                    minStepsBetweenThumbs={1}
                                    value={[
                                        Math.min(range.s, duration),
                                        Math.min(range.e, duration),
                                    ]}
                                    onValueChange={setRangeFromSlider}
                                />
                            ) : null}
                            <div className="grid grid-cols-2 gap-3">
                                <TimeField
                                    label={t('basket.clip.start', 'Start')}
                                    value={range.s}
                                    onChange={s => {
                                        seek(s);
                                        setRange({...range, s});
                                    }}
                                    onSetCurrent={() =>
                                        setRange({...range, s: currentTime})
                                    }
                                    setCurrentLabel={t(
                                        'basket.clip.set_start',
                                        'Start at the current position'
                                    )}
                                />
                                <TimeField
                                    label={t('basket.clip.end', 'End')}
                                    value={range.e}
                                    onChange={e => setRange({...range, e})}
                                    onSetCurrent={() =>
                                        setRange({...range, e: currentTime})
                                    }
                                    setCurrentLabel={t(
                                        'basket.clip.set_end',
                                        'End at the current position'
                                    )}
                                />
                            </div>
                            {rangeInvalid ? (
                                <p className="text-sm text-destructive">
                                    {t(
                                        'basket.clip.invalid_range',
                                        'The end must be after the start.'
                                    )}
                                </p>
                            ) : null}
                        </>
                    ) : null}
                </section>
            ) : null}

            {capabilities.crop ? (
                <section className="space-y-3">
                    <div className="flex flex-wrap items-center gap-4">
                        <LabeledControl label={t('basket.clip.crop', 'Crop')}>
                            <Switch
                                checked={cropEnabled}
                                onCheckedChange={toggleCrop}
                                disabled={!mediaAspect}
                            />
                        </LabeledControl>
                        {cropEnabled ? (
                            <SimpleSelect
                                className="w-40"
                                value={ratio}
                                onValueChange={changeRatio}
                                options={[
                                    {
                                        value: 'free',
                                        label: t(
                                            'basket.clip.ratio.free',
                                            'Free'
                                        ),
                                    },
                                    {
                                        value: 'original',
                                        label: t(
                                            'basket.clip.ratio.original',
                                            'Original'
                                        ),
                                    },
                                    ...cropRatioNames.map(r => ({
                                        value: r,
                                        label: r,
                                    })),
                                ]}
                            />
                        ) : null}
                    </div>
                    {cropEnabled ? (
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'basket.clip.crop_help',
                                'Draw the rectangle to keep on the media, then move or resize it by its corners.'
                            )}
                        </p>
                    ) : null}
                </section>
            ) : null}
        </FormDialog>
    );
}

/**
 * `mm:ss.t` field, committed on blur or Enter; a button takes the current
 * playback position.
 */
function TimeField({
    label,
    value,
    onChange,
    onSetCurrent,
    setCurrentLabel,
}: {
    label: string;
    value: number;
    onChange: (seconds: number) => void;
    onSetCurrent: () => void;
    setCurrentLabel: string;
}) {
    const [text, setText] = useState<string>();
    const commit = () => {
        if (text === undefined) {
            return;
        }
        const parsed = parseClipTime(text);
        setText(undefined);
        if (parsed !== undefined) {
            onChange(parsed);
        }
    };

    return (
        <FormRow label={label}>
            <div className="flex gap-2">
                <Input
                    className="font-mono tabular-nums"
                    value={text ?? formatClipTime(value)}
                    onChange={e => setText(e.target.value)}
                    onBlur={commit}
                    onKeyDown={e => {
                        if (e.key === 'Enter') {
                            e.preventDefault();
                            commit();
                        }
                    }}
                    aria-label={label}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onSetCurrent}
                >
                    {setCurrentLabel}
                </Button>
            </div>
        </FormRow>
    );
}
