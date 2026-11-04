import type {AssetAnnotation} from '@/types/api';

export type {AssetAnnotation};

export type Point = {
    x: number;
    y: number;
};

/**
 * Shapes drawn over an asset. Coordinates and sizes are relative to the
 * rendered media (0..1) so that a shape fits every rendition; `page`
 * (1-based) targets a page of a document. Shared with the API
 * (`AssetAnnotationsInterface`).
 */
export enum AnnotationType {
    Target = 'target',
    Draw = 'draw',
    Circle = 'circle',
    Line = 'line',
    Arrow = 'arrow',
    Text = 'text',
    Rect = 'rect',
    Face = 'face',
    Cue = 'cue',
    TimeRange = 'time_range',
}

export type AnnotationId = string;

export interface TargetAnnotation extends AssetAnnotation {
    type: AnnotationType.Target;
    x: number;
    y: number;
    /** Color */
    c?: string;
    /** Size */
    s?: number;
    page?: number;
}

export interface LineAnnotation extends AssetAnnotation {
    type: AnnotationType.Line | AnnotationType.Arrow;
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    c?: string;
    /** Stroke size */
    s?: number;
    page?: number;
}

export interface CircleAnnotation extends AssetAnnotation {
    type: AnnotationType.Circle;
    x: number;
    y: number;
    r: number;
    /** Border color */
    c?: string;
    /** Fill color */
    f?: string;
    s?: number;
    page?: number;
}

export interface RectangleAnnotation extends AssetAnnotation {
    type: AnnotationType.Rect;
    x: number;
    y: number;
    w: number;
    h: number;
    c?: string;
    f?: string;
    s?: number;
    page?: number;
}

/**
 * A face: the oval inscribed in the box. It has no style option, the
 * client draws it.
 */
export interface FaceAnnotation extends AssetAnnotation {
    type: AnnotationType.Face;
    x: number;
    y: number;
    w: number;
    h: number;
    page?: number;
}

export interface DrawAnnotation extends AssetAnnotation {
    type: AnnotationType.Draw;
    paths: Point[][];
    /** Offset of the paths (a moved drawing) */
    x?: number;
    y?: number;
    c?: string;
    s?: number;
    page?: number;
}

export interface TextAnnotation extends AssetAnnotation {
    type: AnnotationType.Text;
    x: number;
    y: number;
    text: string;
    c?: string;
    /** Font size */
    s?: number;
    page?: number;
}

export interface CueAnnotation extends AssetAnnotation {
    type: AnnotationType.Cue;
    /** Time in seconds */
    t: number;
}

export interface TimeRangeAnnotation extends AssetAnnotation {
    type: AnnotationType.TimeRange;
    /** Start time in seconds */
    s: number;
    /** End time in seconds */
    e: number;
}

/**
 * Options of a tool or of the selected shape, as shown in the toolbar.
 * The size is in pixels of the rendered media (stroke width or font size).
 */
export type AnnotationOptions = {
    color: string;
    /** Rectangles and circles only; none when undefined */
    fillColor?: string;
    size: number;
};
