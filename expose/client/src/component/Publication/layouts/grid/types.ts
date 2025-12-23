import {MouseEvent, CSSProperties, ComponentType} from 'react';

export interface Image {
    width: number;
    height: number;
    orientation?: number;
}

export type ImageExtended<T extends Image = Image> = T & {
    scaledWidth: number;
    scaledHeight: number;
    viewportWidth: number;
    marginLeft: number;
};

export interface BuildLayoutOptions {
    containerWidth: number;
    maxRows?: number;
    rowHeight: number;
    margin: number;
}

export type ImageExtendedRow<T extends Image = Image> = ImageExtended<T>[];

export type StyleFunctionContext<T extends Image = Image> = {
    item: T;
};

export type StyleFunction = (context: StyleFunctionContext) => CSSProperties;

export type StyleProp = CSSProperties | StyleFunction;

export interface ImageProps<T extends ImageExtended = ImageExtended> {
    item: T;
    index: number;
    margin: number;
    isSelectable: boolean;
    onClick: (index: number, event: MouseEvent<HTMLElement>) => void;
    onSelect: (index: number, event: MouseEvent<HTMLElement>) => void;
    tileViewportStyle: StyleProp;
    thumbnailStyle: StyleProp;
    tagStyle: StyleProp;
    height?: number;
    thumbnailImageComponent?: ComponentType<ThumbnailImageProps>;
}

export interface ThumbnailImageComponentImageProps {
    key: string | number;
    src: string;
    alt: string;
    title: string | null;
    style: CSSProperties;
}

export type ThumbnailImageProps<T extends ImageExtended = ImageExtended> =
    ImageProps<T> & {
        imageProps: ThumbnailImageComponentImageProps;
    };
