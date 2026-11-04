'use client';

import {useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {PauseIcon, PlayIcon, PlusIcon, Trash2Icon, XIcon} from 'lucide-react';
import type {ApiFile, Asset} from '@/types/api';
import type {ModalProps} from '@alchemy/next-ui/modals/ModalProvider';
import {FormDialog} from '@alchemy/next-ui/modals/FormDialog';
import {Button} from '@alchemy/next-ui/ui/button';
import {LabeledControl, Slider, Switch} from '@alchemy/next-ui/ui/controls';
import {FormRow, Input} from '@alchemy/next-ui/ui/input';
import {SimpleSelect} from '@alchemy/next-ui/ui/select';
import {useDirtyState} from '@alchemy/next-ui/navigation/unsavedChanges';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {cn} from '@alchemy/next-ui/utils/cn';
import {toastError} from '@alchemy/next-ui/utils/errors';
import {AudioPlayer} from '@/features/assets/player/AudioPlayer';
import {
    type CropRatioName,
    cropRatioNames,
    cropRatios,
} from '@/features/assets/crop/ratios';
import type {ClipCrop, ClipTimeRange, MediaClip} from './types';
import {
    formatClipRange,
    formatClipTime,
    getClipCapabilities,
    MAX_TIME_RANGES,
    normalizeClip,
    normalizeTimeRanges,
    parseClipTime,
} from './clip';
import {CropRectEditor, fitCropToRatio} from './CropRectEditor';
import {useClipPlayback} from './useClipPlayback';
import {VideoFilmstrip} from './VideoFilmstrip';
import {AudioWaveform} from './AudioWaveform';

type RatioChoice = 'free' | 'original' | CropRatioName;

/** Length of a range added at the current position, in seconds */
const DEFAULT_RANGE_DURATION = 10;

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

export type ClipEditorDialogProps = ModalProps<boolean> & {
    asset: Asset;
    initial?: MediaClip | null;
    title: string;
    description: string;
    /** Shown above the editor, e.g. where the initial clip comes from */
    notice?: string;
    removeLabel: string;
    /** Whether a clip is saved, offering to remove it */
    removable?: boolean;
    /** Saves the clip, or removes it with `null` */
    onSave: (clip: MediaClip | null) => Promise<void>;
};

/**
 * Time ranges (video, audio) and crop (image, video) of a media: the region
 * of interest of an asset, the extract of a basket item. The file itself is
 * not cut: the clip is applied when the media is played, published or
 * exported, the ranges one after the other.
 */
export function ClipEditorDialog({
    open,
    onOpenChange,
    resolve,
    asset,
    initial,
    title,
    description,
    notice,
    removeLabel,
    removable = !!initial,
    onSave,
}: ClipEditorDialogProps) {
    const {t} = useTranslation();
    const capabilities = getClipCapabilities(asset.source?.type);
    const media = getMediaFile(asset);
    const mediaKind = getFileKind(media?.type);

    const [ranges, setRanges] = useState<ClipTimeRange[]>(
        capabilities.timeRange ? (initial?.timeRanges ?? []) : []
    );
    const [selected, setSelected] = useState(0);
    const [crop, setCrop] = useState<ClipCrop | undefined>(
        capabilities.crop ? (initial?.crop ?? undefined) : undefined
    );
    const [cropEnabled, setCropEnabled] = useState(!!crop);
    const [ratio, setRatio] = useState<RatioChoice>('free');
    const [duration, setDuration] = useState<number>();
    const [mediaAspect, setMediaAspect] = useState<number>();
    const [peaks, setPeaks] = useState<number[]>();
    const [currentTime, setCurrentTime] = useState(0);
    const [playing, setPlaying] = useState(false);
    const [removing, setRemoving] = useState(false);
    const videoRef = useRef<HTMLVideoElement>(null);

    const {dirty} = useDirtyState({ranges, crop: cropEnabled ? crop : null});

    const ratioValue =
        ratio === 'free'
            ? undefined
            : ratio === 'original'
              ? mediaAspect
              : cropRatios[ratio];

    const range: ClipTimeRange | undefined =
        ranges[Math.min(selected, ranges.length - 1)];
    const selectedIndex = range ? Math.min(selected, ranges.length - 1) : -1;
    const invalidIndexes = ranges
        .map((r, i) => (r.e > r.s ? -1 : i))
        .filter(i => i >= 0);
    const rangeInvalid = invalidIndexes.length > 0;
    const tooMany = ranges.length > MAX_TIME_RANGES;
    // The playback follows the ranges as they will be saved
    const playedRanges = rangeInvalid
        ? []
        : normalizeTimeRanges(ranges, duration);

    useClipPlayback(videoRef, playedRanges);

    const clip = normalizeClip(
        {
            timeRanges: ranges,
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

    const updateRange = (value: ClipTimeRange) => {
        setRanges(rs => rs.map((r, i) => (i === selectedIndex ? value : r)));
    };

    const setRangeFromSlider = ([s, e]: number[]) => {
        if (range && e !== range.e) {
            // Show the last frame of the range, still within it
            seek(Math.max(s, e - 0.1));
        } else if (s !== range?.s) {
            seek(s);
        }
        updateRange({s, e});
    };

    const addRange = () => {
        let s = currentTime;
        let e = s + DEFAULT_RANGE_DURATION;
        if (duration !== undefined && duration > 0) {
            e = Math.min(e, duration);
            if (e <= s) {
                s = Math.max(0, duration - DEFAULT_RANGE_DURATION);
                e = duration;
            }
        }
        setRanges(rs => [...rs, {s, e}]);
        setSelected(ranges.length);
    };

    const removeRange = () => {
        if (selectedIndex < 0) {
            return;
        }
        setRanges(rs => rs.filter((_, i) => i !== selectedIndex));
        setSelected(Math.max(0, selectedIndex - 1));
    };

    const selectRange = (index: number) => {
        setSelected(index);
        seek(ranges[index].s);
    };

    const toggleRanges = (enabled: boolean) => {
        if (!enabled) {
            setRanges([]);
            setSelected(0);

            return;
        }
        const end = duration ?? Math.max(currentTime + 10, 10);
        setRanges([{s: Math.min(currentTime, end), e: end}]);
        setSelected(0);
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
        await onSave(clip);
        resolve?.(true);
    };

    const remove = async () => {
        setRemoving(true);
        try {
            await onSave(null);
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
            title={title}
            description={description}
            submitLabel={t('common.save', 'Save')}
            canSubmit={!rangeInvalid && !tooMany && !removing}
            dirty={dirty}
            onSubmit={submit}
            footerStart={
                removable ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="text-destructive"
                        disabled={removing}
                        onClick={remove}
                        data-testid="clip-remove"
                    >
                        <Trash2Icon /> {removeLabel}
                    </Button>
                ) : null
            }
            bodyClassName="space-y-4"
        >
            {notice ? (
                <p
                    className="rounded-md bg-muted px-3 py-2 text-sm text-muted-foreground"
                    data-testid="clip-notice"
                >
                    {notice}
                </p>
            ) : null}
            {mediaKind === FileKind.Audio && media?.url ? (
                <AudioPlayer
                    src={media.url}
                    timeRanges={playedRanges}
                    onTimeUpdate={setCurrentTime}
                    onDuration={setDuration}
                    onPeaks={setPeaks}
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
                        label={t('clip.time_ranges', 'Time ranges')}
                    >
                        <Switch
                            checked={ranges.length > 0}
                            onCheckedChange={toggleRanges}
                        />
                    </LabeledControl>
                    {ranges.length > 0 ? (
                        <>
                            {duration ? (
                                <RangeTimeline
                                    ranges={ranges}
                                    duration={duration}
                                    selected={selectedIndex}
                                    currentTime={currentTime}
                                    onSelect={selectRange}
                                    videoSrc={
                                        mediaKind === FileKind.Video
                                            ? media?.url
                                            : undefined
                                    }
                                    aspect={mediaAspect}
                                    peaks={
                                        mediaKind === FileKind.Audio
                                            ? peaks
                                            : undefined
                                    }
                                />
                            ) : null}
                            <div
                                className="flex flex-wrap items-center gap-1.5"
                                data-testid="clip-range-list"
                            >
                                {ranges.map((r, i) => (
                                    <Button
                                        // The ranges are edited in place: their index is their identity
                                        key={i}
                                        type="button"
                                        size="sm"
                                        variant={
                                            i === selectedIndex
                                                ? 'secondary'
                                                : 'ghost'
                                        }
                                        className={cn(
                                            'font-mono tabular-nums',
                                            invalidIndexes.includes(i) &&
                                                'text-destructive'
                                        )}
                                        aria-pressed={i === selectedIndex}
                                        onClick={() => selectRange(i)}
                                    >
                                        {formatClipRange(r)}
                                    </Button>
                                ))}
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    onClick={addRange}
                                    disabled={ranges.length >= MAX_TIME_RANGES}
                                    data-testid="clip-add-range"
                                >
                                    <PlusIcon />
                                    {t('clip.add_range', 'Add a range')}
                                </Button>
                            </div>
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
                                            label={t('clip.start', 'Start')}
                                            value={range.s}
                                            onChange={s => {
                                                seek(s);
                                                updateRange({...range, s});
                                            }}
                                            onSetCurrent={() =>
                                                updateRange({
                                                    ...range,
                                                    s: currentTime,
                                                })
                                            }
                                            setCurrentLabel={t(
                                                'clip.set_start',
                                                'Start at the current position'
                                            )}
                                        />
                                        <TimeField
                                            label={t('clip.end', 'End')}
                                            value={range.e}
                                            onChange={e =>
                                                updateRange({...range, e})
                                            }
                                            onSetCurrent={() =>
                                                updateRange({
                                                    ...range,
                                                    e: currentTime,
                                                })
                                            }
                                            setCurrentLabel={t(
                                                'clip.set_end',
                                                'End at the current position'
                                            )}
                                        />
                                    </div>
                                    <div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={removeRange}
                                            data-testid="clip-remove-range"
                                        >
                                            <XIcon />
                                            {t(
                                                'clip.remove_range',
                                                'Remove the range'
                                            )}
                                        </Button>
                                    </div>
                                </>
                            ) : null}
                            {rangeInvalid ? (
                                <p className="text-sm text-destructive">
                                    {t(
                                        'clip.invalid_range',
                                        'The end must be after the start.'
                                    )}
                                </p>
                            ) : null}
                            {ranges.length > 1 ? (
                                <p className="text-xs text-muted-foreground">
                                    {t(
                                        'clip.ranges_help',
                                        'The ranges are played one after the other, and joined in a single file when published or exported. Overlapping ranges are merged.'
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
                        <LabeledControl label={t('clip.crop', 'Crop')}>
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
                                        label: t('clip.ratio.free', 'Free'),
                                    },
                                    {
                                        value: 'original',
                                        label: t(
                                            'clip.ratio.original',
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
                                'clip.crop_help',
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
 * The duration of the media, with every range on it: a click on one selects
 * it. The content of the media is drawn behind, as in an editing software:
 * the thumbnails of a video, the waveform of a sound.
 */
function RangeTimeline({
    ranges,
    duration,
    selected,
    currentTime,
    onSelect,
    videoSrc,
    aspect,
    peaks,
}: {
    ranges: ClipTimeRange[];
    duration: number;
    selected: number;
    currentTime: number;
    onSelect: (index: number) => void;
    videoSrc?: string;
    /** Width / height of the video */
    aspect?: number;
    /** Waveform of the sound, in [-1, 1] */
    peaks?: number[];
}) {
    const percent = (time: number) =>
        Math.min(100, Math.max(0, (time / duration) * 100));
    const background = videoSrc ? (
        <VideoFilmstrip
            src={videoSrc}
            duration={duration}
            aspect={aspect}
            className="absolute inset-0 rounded"
        />
    ) : peaks ? (
        <div className="absolute inset-0 overflow-hidden rounded bg-muted">
            <AudioWaveform peaks={peaks} />
        </div>
    ) : null;
    const withBackground = !!background;

    return (
        <div
            className={cn(
                'relative w-full rounded bg-muted',
                withBackground ? 'h-12' : 'h-6'
            )}
            data-testid="clip-range-timeline"
        >
            {background}
            {ranges.map((r, i) => {
                const left = percent(r.s);
                const width = Math.max(0.5, percent(r.e) - left);

                return (
                    <button
                        // The ranges are edited in place: their index is their identity
                        key={i}
                        type="button"
                        aria-label={formatClipRange(r)}
                        title={formatClipRange(r)}
                        className={cn(
                            'absolute inset-y-0 cursor-pointer rounded outline-none focus-visible:ring-2 focus-visible:ring-ring',
                            withBackground
                                ? 'border-2 border-primary/70 bg-primary/25 hover:bg-primary/40'
                                : 'bg-primary/40 hover:bg-primary/60',
                            i === selected &&
                                (withBackground
                                    ? 'border-primary bg-primary/40 ring-2 ring-primary'
                                    : 'bg-primary ring-2 ring-primary')
                        )}
                        style={{left: `${left}%`, width: `${width}%`}}
                        onClick={() => onSelect(i)}
                    />
                );
            })}
            <div
                className={cn(
                    'pointer-events-none absolute inset-y-0 w-px bg-foreground',
                    // Kept visible on the thumbnails
                    withBackground && 'shadow-[0_0_0_1px_var(--background)]'
                )}
                style={{left: `${percent(currentTime)}%`}}
            />
        </div>
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
