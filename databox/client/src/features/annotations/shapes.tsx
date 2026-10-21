import type {ReactNode} from 'react';
import {
    AnnotationType,
    type AnnotationOptions,
    type AssetAnnotation,
    type CircleAnnotation,
    type DrawAnnotation,
    type FaceAnnotation,
    type LineAnnotation,
    type Point,
    type RectangleAnnotation,
    type TargetAnnotation,
    type TextAnnotation,
} from './types';
import {
    boxFromPoints,
    distance,
    measureTextWidth,
    pointsBox,
    textAscent,
    textFontFamily,
    textLineHeight,
    textLines,
    type Box,
    type MediaSize,
} from './geometry';

export type HandleId =
    | 'nw'
    | 'n'
    | 'ne'
    | 'e'
    | 'se'
    | 's'
    | 'sw'
    | 'w'
    | 'start'
    | 'end'
    | 'radius'
    | 'size';

/** A resize handle of the selected shape, in pixels of the media */
export type Handle = {id: HandleId; x: number; y: number; cursor: string};

export type RenderProps = {
    annotation: AssetAnnotation;
    media: MediaSize;
    /** Minimum width of the hit area of thin shapes, in pixels of the media */
    hitWidth: number;
    /** Pixels of the media per pixel of the screen (1 / zoom) */
    unscale: number;
};

/** A click (no drag) creates a shape of this size, in pixels of the media */
export const defaultShapeSize = 80;
const minSize = 2;

/**
 * What the layer knows of a kind of shape: its geometry (in pixels of the
 * media) and its SVG rendering. Coordinates stay relative in the shape.
 */
export type ShapeModel<A extends AssetAnnotation = AssetAnnotation> = {
    getBox: (a: A, media: MediaSize) => Box;
    /** The shape moved by a delta in pixels */
    move: (a: A, dx: number, dy: number, media: MediaSize) => A;
    getHandles: (a: A, media: MediaSize) => Handle[];
    /** `start` (the shape when the drag began) with the handle dragged to `p` */
    resize: (start: A, handle: HandleId, p: Point, media: MediaSize) => A;
    /** The shape of a drag from `from` to `to`; a click gives a default size */
    create: (
        from: Point,
        to: Point,
        options: AnnotationOptions,
        media: MediaSize
    ) => A;
    toOptions: (a: A, media: MediaSize) => AnnotationOptions;
    applyOptions: (a: A, options: AnnotationOptions, media: MediaSize) => A;
    /** Has a fill color option */
    fillable?: boolean;
    /** Drawn by the client only: no color, fill or size option */
    fixedStyle?: boolean;
    render: (props: RenderProps) => ReactNode;
    /** Drawn under the shapes when the shape is selected or hovered */
    renderMask?: (props: RenderProps) => ReactNode;
};

const strokeOptions = {
    toOptions: (a: AssetAnnotation, media: MediaSize): AnnotationOptions => ({
        color: a.c ?? '#000000',
        size: (a.s ?? 0) * media.width,
    }),
    applyOptions: <A extends AssetAnnotation>(
        a: A,
        options: AnnotationOptions,
        media: MediaSize
    ): A => ({
        ...a,
        c: options.color,
        s: options.size / media.width,
    }),
};

const fillOptions = {
    toOptions: (a: AssetAnnotation, media: MediaSize): AnnotationOptions => ({
        ...strokeOptions.toOptions(a, media),
        fillColor: a.f || undefined,
    }),
    applyOptions: <A extends AssetAnnotation>(
        a: A,
        options: AnnotationOptions,
        media: MediaSize
    ): A => {
        const {f: _f, ...next} = strokeOptions.applyOptions(a, options, media);

        return (
            options.fillColor ? {...next, f: options.fillColor} : next
        ) as A;
    },
};

/** Moves a shape positioned by its `x` / `y` */
function moveXY<A extends AssetAnnotation>(
    a: A,
    dx: number,
    dy: number,
    {width, height}: MediaSize
): A {
    return {
        ...a,
        x: (a.x ?? 0) + dx / width,
        y: (a.y ?? 0) + dy / height,
    };
}

function strokeWidth(a: AssetAnnotation, media: MediaSize): number {
    return Math.max(0.5, (a.s ?? 0) * media.width);
}

function isClick(from: Point, to: Point): boolean {
    return distance(from, to) < 4;
}

// ---------------------------------------------------------------------------
// Target: a dot in a circle with four ticks

const targetRing = 5;
const targetTick = 2.5;
const targetExtent = targetRing + targetTick;

const target: ShapeModel<TargetAnnotation> = {
    getBox: (a, {width, height}) => {
        const r = (a.s ?? 0) * width * targetExtent;

        return {x: a.x * width - r, y: a.y * height - r, w: 2 * r, h: 2 * r};
    },
    move: moveXY,
    getHandles: () => [],
    resize: a => a,
    create: (_from, to, options, media) => ({
        type: AnnotationType.Target,
        x: to.x / media.width,
        y: to.y / media.height,
        c: options.color,
        s: options.size / media.width,
    }),
    ...strokeOptions,
    render: ({annotation, media}) => {
        const a = annotation as TargetAnnotation;
        const cx = a.x * media.width;
        const cy = a.y * media.height;
        const size = strokeWidth(a, media);
        const ring = size * targetRing;
        const tick = size * targetTick;

        return (
            <g stroke={a.c} strokeWidth={size} fill="none">
                <circle cx={cx} cy={cy} r={size} fill={a.c} stroke="none" />
                <circle cx={cx} cy={cy} r={ring} />
                {[
                    [-1, 0],
                    [1, 0],
                    [0, -1],
                    [0, 1],
                ].map(([dx, dy]) => (
                    <line
                        key={`${dx},${dy}`}
                        x1={cx + ring * dx}
                        y1={cy + ring * dy}
                        x2={cx + (ring + tick) * dx}
                        y2={cy + (ring + tick) * dy}
                    />
                ))}
                <circle
                    cx={cx}
                    cy={cy}
                    r={ring + tick}
                    fill="transparent"
                    stroke="none"
                />
            </g>
        );
    },
};

// ---------------------------------------------------------------------------
// Circle

const circle: ShapeModel<CircleAnnotation> = {
    getBox: (a, {width, height}) => {
        const r = a.r * width;

        return {x: a.x * width - r, y: a.y * height - r, w: 2 * r, h: 2 * r};
    },
    move: moveXY,
    getHandles: (a, {width, height}) => [
        {
            id: 'radius',
            x: (a.x + a.r) * width,
            y: a.y * height,
            cursor: 'ew-resize',
        },
    ],
    resize: (start, _handle, p, media) => ({
        ...start,
        r:
            Math.max(
                minSize,
                distance(
                    {x: start.x * media.width, y: start.y * media.height},
                    p
                )
            ) / media.width,
    }),
    create: (from, to, options, media) => {
        const r = isClick(from, to)
            ? defaultShapeSize / 2
            : Math.max(minSize, distance(from, to));

        return {
            type: AnnotationType.Circle,
            x: from.x / media.width,
            y: from.y / media.height,
            r: r / media.width,
            c: options.color,
            ...(options.fillColor ? {f: options.fillColor} : {}),
            s: options.size / media.width,
        };
    },
    ...fillOptions,
    fillable: true,
    render: ({annotation, media}) => {
        const a = annotation as CircleAnnotation;

        return (
            <circle
                cx={a.x * media.width}
                cy={a.y * media.height}
                r={a.r * media.width}
                stroke={a.c}
                strokeWidth={strokeWidth(a, media)}
                fill={a.f || 'transparent'}
            />
        );
    },
};

// ---------------------------------------------------------------------------
// Boxes: rectangle and face

type BoxAnnotation = AssetAnnotation & {
    x: number;
    y: number;
    w: number;
    h: number;
};

const boxHandles: {id: HandleId; fx: number; fy: number; cursor: string}[] = [
    {id: 'nw', fx: 0, fy: 0, cursor: 'nwse-resize'},
    {id: 'n', fx: 0.5, fy: 0, cursor: 'ns-resize'},
    {id: 'ne', fx: 1, fy: 0, cursor: 'nesw-resize'},
    {id: 'e', fx: 1, fy: 0.5, cursor: 'ew-resize'},
    {id: 'se', fx: 1, fy: 1, cursor: 'nwse-resize'},
    {id: 's', fx: 0.5, fy: 1, cursor: 'ns-resize'},
    {id: 'sw', fx: 0, fy: 1, cursor: 'nesw-resize'},
    {id: 'w', fx: 0, fy: 0.5, cursor: 'ew-resize'},
];

function boxToRect(
    box: Box,
    {width, height}: MediaSize
): Pick<BoxAnnotation, 'x' | 'y' | 'w' | 'h'> {
    return {
        x: box.x / width,
        y: box.y / height,
        w: box.w / width,
        h: box.h / height,
    };
}

function getBoxOf(a: BoxAnnotation, {width, height}: MediaSize): Box {
    return {
        x: a.x * width,
        y: a.y * height,
        w: a.w * width,
        h: a.h * height,
    };
}

/** The geometry of the shapes drawn in a box (`x`, `y`, `w`, `h`) */
const boxGeometry = {
    getBox: getBoxOf,
    move: moveXY,
    getHandles: (a: BoxAnnotation, media: MediaSize): Handle[] => {
        const box = getBoxOf(a, media);

        return boxHandles.map(({id, fx, fy, cursor}) => ({
            id,
            x: box.x + box.w * fx,
            y: box.y + box.h * fy,
            cursor,
        }));
    },
    resize: <A extends BoxAnnotation>(
        start: A,
        handle: HandleId,
        p: Point,
        media: MediaSize
    ): A => {
        const box = getBoxOf(start, media);
        let {x: x1, y: y1} = box;
        let x2 = box.x + box.w;
        let y2 = box.y + box.h;
        if (handle.includes('w')) {
            x1 = p.x;
        }
        if (handle.includes('e')) {
            x2 = p.x;
        }
        if (handle.includes('n')) {
            y1 = p.y;
        }
        if (handle.includes('s')) {
            y2 = p.y;
        }

        return {
            ...start,
            ...boxToRect(boxFromPoints({x: x1, y: y1}, {x: x2, y: y2}), media),
        };
    },
};

/** The box of a drag; a click gives a default size, centered on it */
function createBox(
    from: Point,
    to: Point,
    media: MediaSize
): Pick<BoxAnnotation, 'x' | 'y' | 'w' | 'h'> {
    const box = isClick(from, to)
        ? {
              x: from.x - defaultShapeSize / 2,
              y: from.y - defaultShapeSize / 2,
              w: defaultShapeSize,
              h: defaultShapeSize,
          }
        : boxFromPoints(from, to);

    return boxToRect(box, media);
}

const rect: ShapeModel<RectangleAnnotation> = {
    ...boxGeometry,
    create: (from, to, options, media) => ({
        type: AnnotationType.Rect,
        ...createBox(from, to, media),
        c: options.color,
        ...(options.fillColor ? {f: options.fillColor} : {}),
        s: options.size / media.width,
    }),
    ...fillOptions,
    fillable: true,
    render: ({annotation, media}) => {
        const a = annotation as RectangleAnnotation;
        const box = getBoxOf(a, media);

        return (
            <rect
                x={box.x}
                y={box.y}
                width={box.w}
                height={box.h}
                stroke={a.c}
                strokeWidth={strokeWidth(a, media)}
                fill={a.f || 'transparent'}
            />
        );
    },
};

/** The oval of a face: light border over a dark halo, readable on any image */
export const faceStyle = {
    stroke: 'rgba(255, 255, 255, 0.9)',
    strokeWidth: 1.5,
    halo: 'rgba(0, 0, 0, 0.35)',
    haloWidth: 3,
    mask: 'rgba(0, 0, 0, 0.45)',
};

function ellipseOf(a: FaceAnnotation, media: MediaSize) {
    const box = getBoxOf(a, media);

    return {
        cx: box.x + box.w / 2,
        cy: box.y + box.h / 2,
        rx: box.w / 2,
        ry: box.h / 2,
    };
}

const face: ShapeModel<FaceAnnotation> = {
    ...boxGeometry,
    create: (from, to, _options, media) => ({
        type: AnnotationType.Face,
        ...createBox(from, to, media),
    }),
    toOptions: () => ({color: '#ffffff', size: faceStyle.strokeWidth}),
    applyOptions: a => a,
    fixedStyle: true,
    render: ({annotation, media, unscale}) => {
        const ellipse = ellipseOf(annotation as FaceAnnotation, media);

        return (
            <g>
                <ellipse
                    {...ellipse}
                    fill="transparent"
                    stroke={faceStyle.halo}
                    strokeWidth={faceStyle.haloWidth * unscale}
                />
                <ellipse
                    {...ellipse}
                    fill="none"
                    stroke={faceStyle.stroke}
                    strokeWidth={faceStyle.strokeWidth * unscale}
                />
            </g>
        );
    },
    // The image darkened around the face
    renderMask: ({annotation, media}) => {
        const {cx, cy, rx, ry} = ellipseOf(annotation as FaceAnnotation, media);
        const d =
            `M0 0H${media.width}V${media.height}H0Z ` +
            `M${cx - rx} ${cy}` +
            `A${rx} ${ry} 0 1 0 ${cx + rx} ${cy}` +
            `A${rx} ${ry} 0 1 0 ${cx - rx} ${cy}Z`;

        return (
            <path
                d={d}
                fill={faceStyle.mask}
                fillRule="evenodd"
                style={{pointerEvents: 'none'}}
            />
        );
    },
};

// ---------------------------------------------------------------------------
// Line and arrow

function lineModel(
    type: AnnotationType.Line | AnnotationType.Arrow
): ShapeModel<LineAnnotation> {
    const ends = (a: LineAnnotation, {width, height}: MediaSize) => ({
        x1: a.x1 * width,
        y1: a.y1 * height,
        x2: a.x2 * width,
        y2: a.y2 * height,
    });

    return {
        getBox: (a, media) => {
            const {x1, y1, x2, y2} = ends(a, media);

            return boxFromPoints({x: x1, y: y1}, {x: x2, y: y2});
        },
        move: (a, dx, dy, {width, height}) => ({
            ...a,
            x1: a.x1 + dx / width,
            x2: a.x2 + dx / width,
            y1: a.y1 + dy / height,
            y2: a.y2 + dy / height,
        }),
        getHandles: (a, media) => {
            const {x1, y1, x2, y2} = ends(a, media);

            return [
                {id: 'start', x: x1, y: y1, cursor: 'move'},
                {id: 'end', x: x2, y: y2, cursor: 'move'},
            ];
        },
        resize: (start, handle, p, {width, height}) =>
            handle === 'start'
                ? {...start, x1: p.x / width, y1: p.y / height}
                : {...start, x2: p.x / width, y2: p.y / height},
        create: (from, to, options, {width, height}) => {
            if (isClick(from, to)) {
                to = {x: from.x + defaultShapeSize, y: from.y};
            }

            return {
                type,
                x1: from.x / width,
                y1: from.y / height,
                x2: to.x / width,
                y2: to.y / height,
                c: options.color,
                s: options.size / width,
            };
        },
        ...strokeOptions,
        render: ({annotation, media, hitWidth}) => {
            const a = annotation as LineAnnotation;
            const {x1, y1, x2, y2} = ends(a, media);
            const size = strokeWidth(a, media);
            let head: ReactNode = null;
            let shaft = {x2, y2};
            if (type === AnnotationType.Arrow) {
                const angle = Math.atan2(y2 - y1, x2 - x1);
                const length = size * 4 + 6;
                const opening = Math.PI / 7;
                const base = {
                    x: x2 - length * Math.cos(angle),
                    y: y2 - length * Math.sin(angle),
                };
                shaft = {
                    x2: (x2 + base.x) / 2,
                    y2: (y2 + base.y) / 2,
                };
                const wing = (side: number) =>
                    `${x2 - length * Math.cos(angle + side * opening)},${
                        y2 - length * Math.sin(angle + side * opening)
                    }`;
                head = (
                    <polygon
                        points={`${x2},${y2} ${wing(1)} ${wing(-1)}`}
                        fill={a.c}
                        stroke="none"
                    />
                );
            }

            return (
                <g stroke={a.c} strokeLinecap="round">
                    <line
                        x1={x1}
                        y1={y1}
                        x2={shaft.x2}
                        y2={shaft.y2}
                        strokeWidth={size}
                    />
                    {head}
                    <line
                        x1={x1}
                        y1={y1}
                        x2={x2}
                        y2={y2}
                        stroke="transparent"
                        strokeWidth={Math.max(size, hitWidth)}
                    />
                </g>
            );
        },
    };
}

// ---------------------------------------------------------------------------
// Free hand drawing

function drawPoints(a: DrawAnnotation, {width, height}: MediaSize): Point[][] {
    const ox = a.x ?? 0;
    const oy = a.y ?? 0;

    return a.paths.map(path =>
        path.map(p => ({x: (p.x + ox) * width, y: (p.y + oy) * height}))
    );
}

export function drawPathData(paths: Point[][]): string {
    return paths
        .map(path =>
            path
                .map(
                    (p, i) =>
                        `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)} ${p.y.toFixed(1)}`
                )
                .join(' ')
        )
        .join(' ');
}

const draw: ShapeModel<DrawAnnotation> = {
    getBox: (a, media) => pointsBox(drawPoints(a, media).flat()),
    move: moveXY,
    getHandles: () => [],
    resize: a => a,
    // Strokes are gathered by the layer, which adds the paths itself
    create: (_from, _to, options, media) => ({
        type: AnnotationType.Draw,
        paths: [],
        c: options.color,
        s: options.size / media.width,
    }),
    ...strokeOptions,
    render: ({annotation, media, hitWidth}) => {
        const a = annotation as DrawAnnotation;
        const d = drawPathData(drawPoints(a, media));
        const size = strokeWidth(a, media);

        return (
            <g
                stroke={a.c}
                fill="none"
                strokeLinecap="round"
                strokeLinejoin="round"
            >
                <path d={d} strokeWidth={size} />
                <path
                    d={d}
                    stroke="transparent"
                    strokeWidth={Math.max(size, hitWidth)}
                />
            </g>
        );
    },
};

// ---------------------------------------------------------------------------
// Text

export function textFontSize(a: TextAnnotation, media: MediaSize): number {
    return Math.max(1, (a.s ?? 0) * media.width);
}

const text: ShapeModel<TextAnnotation> = {
    getBox: (a, media) => {
        const fontSize = textFontSize(a, media);
        const lines = textLines(a.text || ' ');

        return {
            x: a.x * media.width,
            y: a.y * media.height - fontSize * textAscent,
            w: Math.max(
                ...lines.map(l => measureTextWidth(l || ' ', fontSize))
            ),
            h: fontSize * (1 + (lines.length - 1) * textLineHeight),
        };
    },
    move: moveXY,
    getHandles: (a, media) => {
        const box = text.getBox(a, media);

        return [
            {
                id: 'size',
                x: box.x + box.w,
                y: box.y + box.h,
                cursor: 'nwse-resize',
            },
        ];
    },
    resize: (start, _handle, p, media) => {
        const box = text.getBox(start, media);
        const factor = Math.max(0.05, (p.x - box.x) / Math.max(1, box.w));
        const fontSize = Math.max(6, textFontSize(start, media) * factor);

        return {...start, s: fontSize / media.width};
    },
    create: (_from, to, options, media) => ({
        type: AnnotationType.Text,
        x: to.x / media.width,
        y: to.y / media.height,
        text: '',
        c: options.color,
        s: options.size / media.width,
    }),
    ...strokeOptions,
    render: ({annotation, media}) => {
        const a = annotation as TextAnnotation;
        const x = a.x * media.width;

        return (
            <text
                x={x}
                y={a.y * media.height}
                fill={a.c}
                fontSize={textFontSize(a, media)}
                fontFamily={textFontFamily}
                style={{whiteSpace: 'pre'}}
            >
                {textLines(a.text).map((line, i) => (
                    <tspan
                        key={i}
                        x={x}
                        dy={i ? `${textLineHeight}em` : undefined}
                    >
                        {line || ' '}
                    </tspan>
                ))}
            </text>
        );
    },
};

// ---------------------------------------------------------------------------

/** The shapes drawn over an image. Time based ones (cue, range) have no tool. */
const models: Partial<Record<AnnotationType, ShapeModel<any>>> = {
    [AnnotationType.Target]: target,
    [AnnotationType.Circle]: circle,
    [AnnotationType.Rect]: rect,
    [AnnotationType.Face]: face,
    [AnnotationType.Line]: lineModel(AnnotationType.Line),
    [AnnotationType.Arrow]: lineModel(AnnotationType.Arrow),
    [AnnotationType.Draw]: draw,
    [AnnotationType.Text]: text,
};

export function getShapeModel(
    type: string | undefined
): ShapeModel | undefined {
    return type ? models[type as AnnotationType] : undefined;
}

export function getShapeBox(
    a: AssetAnnotation,
    media: MediaSize
): Box | undefined {
    return getShapeModel(a.type)?.getBox(a, media);
}
