import {File} from '../../../../types';
import {AssetAnnotation, OnNewAnnotation} from "../Annotations/annotationTypes.ts";

export type FileWithUrl = {
    url: string;
} & File;

export type Dimensions = {
    width: number;
    height?: number;
};

export type StrictDimensions = {
    width: number;
    height: number;
};

export function createStrictDimensions({
    width,
    height,
}: Dimensions): StrictDimensions {
    return {
        width,
        height: height ?? width,
    };
}

export type PlayerProps = {
    file: FileWithUrl;
    dimensions?: Dimensions | undefined;
    onLoad?: (() => void) | undefined;
    noInteraction?: boolean | undefined;
    zoomEnabled?: boolean | undefined;
    title: string | undefined;
    controls?: boolean | undefined;
    onNewAnnotation?: OnNewAnnotation | undefined;
    annotations?: AssetAnnotation[] | undefined;
};
