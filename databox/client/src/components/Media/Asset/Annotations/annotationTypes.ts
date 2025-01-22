import {MutableRefObject} from 'react';

export type Point = {
    x: number;
    y: number;
};

export enum AnnotationType {
    Target = 'target',
    Draw = 'draw',
    Circle = 'circle',
    Line = 'line',
    Arrow = 'arrow',
    Text = 'text',
    Rect = 'rect',
    Cue = 'cue',
    TimeRange = 'time_range',
}

export type AnnotationId = string;

export interface AssetAnnotation {
    id?: AnnotationId;
    type: AnnotationType;
    name?: string;
    [prop: string]: any;
}

export interface PointAnnotation extends AssetAnnotation {
    type: AnnotationType.Target;
    x: number;
    y: number;
    c?: string; // Color
    s?: number; // Size
    page?: number;
}

export interface LineAnnotation extends AssetAnnotation {
    type: AnnotationType.Target;
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    c?: string; // Color
    s?: number; // Size
    page?: number;
}

export interface CircleAnnotation extends AssetAnnotation {
    type: AnnotationType.Circle;
    x: number;
    y: number;
    r: number;
    c?: string; // Border color
    f?: string; // Fill color
    s?: number; // Stroke size
    page?: number;
}

export type AnnotationOptions = {
    color: string;
    fillColor?: string;
    size: number;
};

export interface RectangleAnnotation extends AssetAnnotation {
    type: AnnotationType.Rect;
    x: number;
    y: number;
    w: number;
    h: number;
    c?: string; // Border color
    f?: string; // Fill color
    s?: number; // Stroke size
}

export interface DrawAnnotation extends AssetAnnotation {
    type: AnnotationType.Draw;
    paths: Point[][];
    x: number;
    y: number;
    c?: string; // Color
    s?: number; // Line width
}

export interface TextAnnotation extends AssetAnnotation {
    type: AnnotationType.Text;
    x: number;
    y: number;
    text: string;
    c?: string; // Color
    s?: number; // Text size
}

export interface CueAnnotation extends AssetAnnotation {
    type: AnnotationType.Cue;
    t: number; // Time in seconds
}

export interface TimeRangeAnnotation extends AssetAnnotation {
    type: AnnotationType.TimeRange;
    s: number; // Start time in seconds
    e: number; // End time in seconds
}

export type AnnotationsControl = {
    onNew: OnNewAnnotation;
    onUpdate: OnUpdateAnnotation;
    onDelete: OnDeleteAnnotation;
};

export type AnnotationsControlRef = MutableRefObject<
    AnnotationsControl | undefined
>;

export type SelectedAnnotationRef = MutableRefObject<
    AssetAnnotation | undefined
>;

export type OnNewAnnotation = (annotation: AssetAnnotation) => void;
export type OnUpdateAnnotation = (
    id: AnnotationId,
    newAnnotation: AssetAnnotation
) => AssetAnnotation;

export type OnDeleteAnnotation = (id: AnnotationId) => void;
