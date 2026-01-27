import {ApiFile} from '../../../../types';
import {
    AssetAnnotation,
    AssetAnnotationRef,
} from '../Annotations/annotationTypes.ts';
import {BaseAnnotationProps} from '../Annotations/AnnotateWrapper.tsx';
import {RefObject} from 'react';
import {ReactZoomPanPinchContentRef} from 'react-zoom-pan-pinch';
import {Dimensions} from '@alchemy/core';

export type FileWithUrl = {
    url: string;
} & ApiFile;

export type PlayerProps = {
    file: FileWithUrl;
    dimensions?: Dimensions | undefined;
    onLoad?: (() => void) | undefined;
    noInteraction?: boolean | undefined;
    zoomEnabled?: boolean | undefined;
    title: string | undefined;
    controls?: boolean | undefined;
    assetAnnotationsRef?: AssetAnnotationRef | undefined;
    annotations?: AssetAnnotation[] | undefined;
    trackingId?: string;
} & BaseAnnotationProps;

export const filePlayerRelativeWrapperClassName = 'fprw';

export type ZoomStepState = {
    current: number;
    maxReached: number;
};

export type ZoomPanPinchContentRef =
    RefObject<ReactZoomPanPinchContentRef | null>;
