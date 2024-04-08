import {File} from '../../../../types';

export type FileWithUrl = {
    url: string;
} & File;

export type Dimensions = {
    width: number;
    height: number;
};

export function createDimensions(width: number, height?: number): Dimensions {
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
};
