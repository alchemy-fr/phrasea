import {MutableRefObject} from 'react';

export type Point = {
    x: number;
    y: number;
};

export enum AnnotationType {
    Point = 'point',
    Draw = 'draw',
    Highlight = 'highlight',
    Circle = 'circle',
    Rect = 'rect',
    Cue = 'cue',
    TimeRange = 'time_range',
}

export interface AssetAnnotation {
    type: AnnotationType;
    name?: string;
    [prop: string]: any;
}

export interface PointAnnotation extends AssetAnnotation {
    type: AnnotationType.Point;
    x: number;
    y: number;
    c?: string; // Color
    s?: number; // Size
    page?: number;
}

export interface CircleAnnotation extends AssetAnnotation {
    type: AnnotationType.Circle;
    x: number;
    y: number;
    radius: number;
    c?: string; // Border color
    f?: string; // Fill color
    s?: number; // Stroke size
    page?: number;
}

export type AnnotationOptions = {
    color: string;
    size: number;
};

export interface RectangleAnnotation extends AssetAnnotation {
    type: AnnotationType.Rect;
    x1: number;
    y1: number;
    x2: number;
    y2: number;
    c?: string; // Border color
    f?: string; // Fill color
    s?: number; // Stroke size
}

export interface DrawAnnotation extends AssetAnnotation {
    type: AnnotationType.Draw;
    paths: Point[][];
    c?: string; // Color
    s?: number; // Line width
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

export type OnNewAnnotation = (annotation: AssetAnnotation) => void;
export type OnNewAnnotationRef = MutableRefObject<OnNewAnnotation | undefined>;
