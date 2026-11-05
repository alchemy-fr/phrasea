/** Aspect ratios offered to crop a media, width / height */
export const cropRatios = {
    '1:1': 1,
    '4:3': 4 / 3,
    '3:4': 3 / 4,
    '16:9': 16 / 9,
} as const;

export type CropRatioName = keyof typeof cropRatios;

export const cropRatioNames = Object.keys(cropRatios) as CropRatioName[];

/** A box relative to its container (0..1) */
export type RelativeBox = {
    left: number;
    top: number;
    width: number;
    height: number;
};

/**
 * Box of a media of the given ratio rendered `object-fit: contain` inside a
 * container of another ratio, relative to the container.
 */
export function containBox(
    mediaAspect: number,
    containerAspect: number = 1
): RelativeBox {
    if (mediaAspect >= containerAspect) {
        const height = containerAspect / mediaAspect;

        return {left: 0, top: (1 - height) / 2, width: 1, height};
    }
    const width = mediaAspect / containerAspect;

    return {left: (1 - width) / 2, top: 0, width, height: 1};
}
