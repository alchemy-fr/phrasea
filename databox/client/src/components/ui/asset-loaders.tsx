import type {CSSProperties} from 'react';
import {FileTextIcon, FilmIcon, ImageIcon, MusicIcon} from 'lucide-react';
import {cn} from '@/lib/utils/cn';

/*
 * Loaders shown while assets are being fetched. Unlike the generic Spinner,
 * each one depicts the thing that is loading — thumbnails, a stack of media,
 * a filmstrip — so the wait reads as "assets are on their way". The
 * keyframes live in app/globals.css (`--animate-asset-*`). All of them are
 * pure CSS: nothing to unmount, and `motion-reduce` freezes them into a
 * static placeholder.
 */

type LoaderProps = {
    className?: string;
    /** Accessible name and optional visible caption */
    label?: string;
};

const stagger = (ms: number): CSSProperties => ({animationDelay: `${ms}ms`});

function Caption({label}: {label?: string}) {
    return label ? (
        <div className="text-sm text-muted-foreground">{label}</div>
    ) : null;
}

/**
 * A grid of thumbnail placeholders: a pulse wave travels diagonally across
 * the tiles while a light band sweeps over them, like thumbnails about to
 * be painted in.
 */
export function AssetGridLoader({
    className,
    label,
    columns = 4,
    rows = 3,
}: LoaderProps & {columns?: number; rows?: number}) {
    const tiles = Array.from({length: columns * rows}, (_, i) => ({
        row: Math.floor(i / columns),
        col: i % columns,
    }));

    return (
        <div
            role="status"
            aria-label={label}
            data-slot="asset-grid-loader"
            className={cn(
                'flex flex-col items-center justify-center gap-4',
                className
            )}
        >
            <div className="relative overflow-hidden rounded-lg p-1">
                <div
                    className="grid max-w-full gap-2"
                    style={{
                        // Tiles shrink together when the column is narrower
                        width: columns * 56 + (columns - 1) * 8,
                        gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))`,
                    }}
                >
                    {tiles.map(({row, col}) => (
                        <div
                            key={`${row}-${col}`}
                            className="aspect-square rounded-md bg-muted-foreground/15 animate-asset-tile motion-reduce:animate-none"
                            style={stagger((row + col) * 90)}
                        />
                    ))}
                </div>
                <div
                    aria-hidden
                    className="pointer-events-none absolute inset-y-0 -left-1/2 w-1/2 bg-linear-to-r from-transparent via-background/70 to-transparent animate-asset-shimmer motion-reduce:hidden"
                />
            </div>
            <Caption label={label} />
        </div>
    );
}

const stackCards = [
    {Icon: ImageIcon, delay: 0},
    {Icon: FilmIcon, delay: -1200},
    {Icon: FileTextIcon, delay: -2400},
];

/**
 * Three media cards fanned out like a pile of photos: the front one keeps
 * sliding away and slipping under the pile, so a new asset always surfaces.
 */
export function AssetStackLoader({className, label}: LoaderProps) {
    return (
        <div
            role="status"
            aria-label={label}
            data-slot="asset-stack-loader"
            className={cn(
                'flex flex-col items-center justify-center gap-5',
                className
            )}
        >
            <div className="relative h-20 w-24">
                {stackCards.map(({Icon, delay}, i) => (
                    <div
                        key={i}
                        className="absolute inset-0 flex items-center justify-center rounded-md border bg-card text-muted-foreground shadow-sm animate-asset-stack motion-reduce:animate-none"
                        style={{
                            ...stagger(delay),
                            // Static fallback (reduced motion): keep the fan
                            zIndex: stackCards.length - i,
                            transform: `translate(${i * 6}px, ${-i * 6}px) rotate(${i * 4}deg) scale(${1 - i * 0.04})`,
                        }}
                    >
                        <Icon className="size-7" strokeWidth={1.5} />
                    </div>
                ))}
            </div>
            <Caption label={label} />
        </div>
    );
}

const filmstripFrames = [
    ImageIcon,
    FilmIcon,
    MusicIcon,
    FileTextIcon,
    ImageIcon,
];

/**
 * A strip of film whose frames develop one after the other, left to right,
 * then fade out before the next pass.
 */
export function AssetFilmstripLoader({className, label}: LoaderProps) {
    return (
        <div
            role="status"
            aria-label={label}
            data-slot="asset-filmstrip-loader"
            className={cn(
                'flex flex-col items-center justify-center gap-4',
                className
            )}
        >
            <div className="flex flex-col gap-1 rounded-md bg-foreground/85 p-1.5 dark:bg-foreground/20">
                <Sprockets count={filmstripFrames.length * 3} />
                <div className="flex gap-1.5 px-0.5">
                    {filmstripFrames.map((Icon, i) => (
                        <div
                            key={i}
                            className="flex size-9 items-center justify-center rounded-xs bg-primary text-primary-foreground animate-asset-frame motion-reduce:animate-none"
                            style={stagger(i * 260)}
                        >
                            <Icon className="size-4" strokeWidth={1.75} />
                        </div>
                    ))}
                </div>
                <Sprockets count={filmstripFrames.length * 3} />
            </div>
            <Caption label={label} />
        </div>
    );
}

function Sprockets({count}: {count: number}) {
    return (
        <div aria-hidden className="flex justify-between px-1">
            {Array.from({length: count}, (_, i) => (
                <span
                    key={i}
                    className="size-1 rounded-[1px] bg-background/70"
                />
            ))}
        </div>
    );
}
