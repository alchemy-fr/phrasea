import {StrictDimensions} from '@alchemy/core';

export type AssetFile = {
    id: string;
    name: string;
    type: string;
    url: string;
};

export type FilePlayerProps = {
    file: AssetFile;
    onLoad?: (() => void) | undefined;
    noInteraction?: boolean | undefined;
    title: string | undefined;
    controls?: boolean | undefined;
    autoPlayable?: boolean | undefined;
    dimensions: StrictDimensions;
};

export type ZoomStepState = {
    current: number;
    maxReached: number;
};
