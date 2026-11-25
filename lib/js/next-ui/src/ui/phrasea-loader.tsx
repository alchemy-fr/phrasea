'use client';

import {useEffect, useId, useRef, useState, type CSSProperties} from 'react';
import {cn} from '../utils/cn';

/*
 * The Phrasea logo as a loader. The cube is drawn in the 443.4 × 512 box of
 * brand/phrasea.svg, its six facets as separate polygons so that each
 * animation moves them on its own; the keyframes live in styles/base.css
 * (`--animate-phrasea-*`). The lines between the three faces of the cube are
 * real gaps, not white strokes: the loader sits on any background.
 */

export const PHRASEA_LOADER_VARIANTS = [
    'ring',
    'breathe',
    'build',
    'burst',
    'turn',
    'pistons',
    'draw',
    'sheen',
    'bounce',
    'orbit',
] as const;

export type PhraseaLoaderVariant = (typeof PHRASEA_LOADER_VARIANTS)[number];

type Motion = {className: string; style?: CSSProperties};

type Facet = {
    color: string;
    points: string;
    /** Rank clockwise around the center, from the top right facet */
    turn: number;
    /** Face of the cube the facet belongs to: 0 top, 1 right, 2 left */
    face: number;
    gradient: [x1: number, y1: number, x2: number, y2: number];
    stops: string[];
};

const CENTER = '221.7px 256px';
// The motions overflow the logo: the drawing area leaves them 60 units around it
const VIEW_BOX = '-60 -60 563.4 632';

/*
 * In paint order: the first facet of each face runs 1.2 units under its
 * neighbour, which hides the hairline antialiasing leaves between two shapes
 * sharing an edge.
 */
const FACETS: Facet[] = [
    {
        color: 'orange',
        points: '221.7,0 2.3,126.6 222.9,254 222.9,0.7',
        turn: 5,
        face: 0,
        gradient: [252.3, 33.9, 66.2, 201.5],
        stops: ['#f18802', '#f18c00', '#f08106', '#eb5e1d', '#e84c29'],
    },
    {
        color: 'yellow',
        points: '221.7,0 441.1,126.6 221.7,253.3',
        turn: 0,
        face: 0,
        gradient: [203.7, 113.4, 439.8, 150.8],
        stops: ['#f0a311', '#f8bb07', '#fdcd00', '#fdcb01', '#ffd200'],
    },
    {
        color: 'green',
        points: '443.4,130.7 443.4,384 442.2,384.7 222.9,258',
        turn: 1,
        face: 1,
        gradient: [452, 250.2, 222.4, 266.3],
        stops: ['#41a589', '#41a589', '#3b9e85', '#288677', '#1d786e'],
    },
    {
        color: 'blue',
        points: '224,257.4 443.4,384 224,510.6',
        turn: 2,
        face: 1,
        gradient: [213.2, 261.8, 341.8, 452.5],
        stops: ['#006280', '#0b7091', '#2488ad', '#3ea0c9', '#54b4e1'],
    },
    {
        color: 'red',
        points: '220.5,258 1.2,384.7 0,384 0,130.7',
        turn: 4,
        face: 2,
        gradient: [221.3, 248.3, -6.5, 260.2],
        stops: ['#b82e45', '#c32f48', '#b32d43', '#8b2735', '#6d232b'],
    },
    {
        color: 'purple',
        points: '219.3,510.6 0,384 219.3,257.4',
        turn: 3,
        face: 2,
        gradient: [251.2, 287.6, 68.8, 457.7],
        stops: ['#5a3a78', '#5e3d7d', '#533670', '#3b2754', '#251a3b'],
    },
];

const HEXAGON = [
    [221.7, 0],
    [443.4, 128],
    [443.4, 384],
    [221.7, 512],
    [0, 384],
    [0, 128],
];

// One per side of the hexagon, clockwise from the top: the color of the facet behind it
const SIDE_COLORS = [
    '#fdcd00',
    '#41a589',
    '#3ea0c9',
    '#5a3a78',
    '#c32f48',
    '#f18c00',
];

const ORBIT_SCALE = 1.14;

/** The hexagon of the logo, grown from its center */
function hexagon(scale: number): string[][] {
    return HEXAGON.map(([x, y]) => [
        (221.7 + (x - 221.7) * scale).toFixed(1),
        (256 + (y - 256) * scale).toFixed(1),
    ]);
}

/** Offset read by the keyframes that push a facet away from the center */
function shift(degrees: number, distance: number): CSSProperties {
    const angle = (degrees * Math.PI) / 180;

    return {
        '--phrasea-x': `${(Math.cos(angle) * distance).toFixed(1)}px`,
        '--phrasea-y': `${(Math.sin(angle) * distance).toFixed(1)}px`,
    } as CSSProperties;
}

const FACET_MOTIONS: Partial<
    Record<PhraseaLoaderVariant, (facet: Facet) => Motion>
> = {
    ring: ({turn}) => ({
        className: 'animate-phrasea-ring motion-reduce:animate-none',
        style: {animationDelay: `${turn * 200 - 1200}ms`},
    }),
    build: ({turn}) => ({
        className: 'animate-phrasea-build motion-reduce:animate-none',
        style: {transformOrigin: CENTER, animationDelay: `${turn * 120}ms`},
    }),
    burst: ({turn}) => ({
        className: 'animate-phrasea-burst motion-reduce:animate-none',
        style: shift(turn * 60 - 60, 48),
    }),
    pistons: ({face}) => ({
        className: 'animate-phrasea-pistons motion-reduce:animate-none',
        style: {
            ...shift(face * 120 - 90, 46),
            animationDelay: `${face * 600 - 1800}ms`,
        },
    }),
    draw: ({turn}) => ({
        className: 'animate-phrasea-draw-facet motion-reduce:animate-none',
        style: {animationDelay: `${turn * 130}ms`},
    }),
};

const LOGO_MOTIONS: Partial<Record<PhraseaLoaderVariant, Motion>> = {
    breathe: {
        className: 'animate-phrasea-breathe motion-reduce:animate-none',
        style: {transformOrigin: CENTER},
    },
    turn: {
        className: 'animate-phrasea-turn motion-reduce:animate-none',
        style: {transformOrigin: CENTER},
    },
    bounce: {
        className: 'animate-phrasea-bounce motion-reduce:animate-none',
        style: {transformOrigin: '221.7px 512px'},
    },
};

let lastPicked: PhraseaLoaderVariant | undefined;

/** Never the variant of the previous loader: two waits in a row look different */
function pickVariant(): PhraseaLoaderVariant {
    const candidates = PHRASEA_LOADER_VARIANTS.filter(v => v !== lastPicked);
    lastPicked = candidates[Math.floor(Math.random() * candidates.length)];

    return lastPicked;
}

type Props = {
    /** Forces one animation; picked at random on every mount otherwise */
    variant?: PhraseaLoaderVariant;
    /** Accessible name */
    label?: string;
    /** Sizes the loader by its height (`h-12` by default) */
    className?: string;
};

export function PhraseaLoader({variant, label, className}: Props) {
    const id = useId();
    const [picked, setPicked] = useState<PhraseaLoaderVariant>();
    const pick = useRef<PhraseaLoaderVariant>(undefined);

    // Picked after mount: a choice made while rendering would differ between
    // the server and the client. The logo stands still until then. The ref
    // makes the effect draw once per loader, however often React runs it
    // (Strict Mode runs it twice).
    useEffect(() => {
        if (!variant) {
            pick.current ??= pickVariant();
            setPicked(pick.current);
        }
    }, [variant]);

    const current = variant ?? picked;
    const url = (name: string) => `url(#${id}-${name})`;
    const facetMotion = current ? FACET_MOTIONS[current] : undefined;
    const logoMotion = current ? LOGO_MOTIONS[current] : undefined;

    return (
        <div
            role="status"
            aria-label={label}
            data-slot="phrasea-loader"
            data-variant={current}
            className={cn('inline-flex h-12 shrink-0', className)}
        >
            <svg
                viewBox={VIEW_BOX}
                aria-hidden="true"
                className="h-full w-auto overflow-visible"
            >
                <defs>
                    {FACETS.map(
                        ({color, gradient: [x1, y1, x2, y2], stops}) => (
                            <linearGradient
                                key={color}
                                id={`${id}-${color}`}
                                gradientUnits="userSpaceOnUse"
                                x1={x1}
                                y1={y1}
                                x2={x2}
                                y2={y2}
                            >
                                {stops.map((stop, i) => (
                                    <stop
                                        key={i}
                                        offset={i / (stops.length - 1)}
                                        stopColor={stop}
                                    />
                                ))}
                            </linearGradient>
                        )
                    )}
                    {current === 'sheen' ? (
                        <clipPath id={`${id}-facets`}>
                            {FACETS.map(({color, points}) => (
                                <polygon key={color} points={points} />
                            ))}
                        </clipPath>
                    ) : null}
                    {current === 'orbit' ? (
                        <mask
                            id={`${id}-orbit`}
                            maskUnits="userSpaceOnUse"
                            x={-60}
                            y={-60}
                            width={563.4}
                            height={632}
                        >
                            <polygon
                                points={hexagon(ORBIT_SCALE).join(' ')}
                                fill="none"
                                stroke="#fff"
                                strokeWidth={34}
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                pathLength={6}
                                strokeDasharray="2 4"
                                className="animate-phrasea-orbit motion-reduce:animate-none"
                            />
                        </mask>
                    ) : null}
                </defs>

                {current === 'bounce' ? (
                    <ellipse
                        cx={221.7}
                        cy={548}
                        rx={150}
                        ry={13}
                        className="animate-phrasea-shadow fill-foreground opacity-15 motion-reduce:animate-none"
                        style={{transformOrigin: '221.7px 548px'}}
                    />
                ) : null}

                {current === 'orbit' ? (
                    <>
                        <polygon
                            points={hexagon(ORBIT_SCALE).join(' ')}
                            fill="none"
                            strokeWidth={20}
                            strokeLinejoin="round"
                            className="stroke-foreground/10"
                        />
                        <g mask={url('orbit')}>
                            <Sides scale={ORBIT_SCALE} strokeWidth={20} />
                        </g>
                    </>
                ) : null}

                <g
                    transform={
                        current === 'orbit'
                            ? 'translate(221.7 256) scale(0.64) translate(-221.7 -256)'
                            : undefined
                    }
                >
                    <g
                        className={logoMotion?.className}
                        style={logoMotion?.style}
                    >
                        {FACETS.map(facet => {
                            const motion = facetMotion?.(facet);

                            return (
                                <polygon
                                    key={facet.color}
                                    data-facet={facet.color}
                                    points={facet.points}
                                    fill={url(facet.color)}
                                    className={motion?.className}
                                    style={motion?.style}
                                />
                            );
                        })}
                    </g>
                </g>

                {current === 'draw' ? (
                    <Sides
                        scale={1}
                        strokeWidth={16}
                        pathLength={1}
                        strokeDasharray="1 2"
                        className="animate-phrasea-draw-edge motion-reduce:hidden"
                        delay={130}
                    />
                ) : null}

                {current === 'sheen' ? (
                    <g clipPath={url('facets')}>
                        <g transform="skewX(-25)">
                            <rect
                                x={0}
                                y={-20}
                                width={150}
                                height={560}
                                fill="#fff"
                                fillOpacity={0.5}
                                className="animate-phrasea-sheen motion-reduce:hidden"
                            />
                        </g>
                    </g>
                ) : null}
            </svg>
        </div>
    );
}

/** The six sides of the hexagon, each in the color of its facet */
function Sides({
    scale,
    delay,
    className,
    ...line
}: {
    scale: number;
    /** Stagger between two sides, in ms */
    delay?: number;
    className?: string;
    strokeWidth: number;
    pathLength?: number;
    strokeDasharray?: string;
}) {
    const corners = hexagon(scale);

    return corners.map(([x1, y1], i) => {
        const [x2, y2] = corners[(i + 1) % corners.length];

        return (
            <line
                key={i}
                x1={x1}
                y1={y1}
                x2={x2}
                y2={y2}
                stroke={SIDE_COLORS[i]}
                strokeLinecap="round"
                className={className}
                style={
                    delay === undefined
                        ? undefined
                        : {animationDelay: `${i * delay}ms`}
                }
                {...line}
            />
        );
    });
}
