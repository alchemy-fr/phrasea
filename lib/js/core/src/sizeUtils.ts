import {StrictDimensions} from './types';

type Step<T> = {
    [size: string | number]: T;
};

type Size<T extends string> = {
    i: number;
    r: T;
};

export function getSizeCase<T extends string>(
    size: number | undefined,
    steps: Step<T>
): T | undefined {
    if (undefined !== size) {
        const sizes: Size<T>[] = Object.keys(steps).map(s => ({
            i: parseInt(s),
            r: steps[s],
        }));
        sizes.sort((a, b) => b.i - a.i);

        for (const s of sizes) {
            if (size >= s.i) {
                return s.r;
            }
        }
    }

    return undefined;
}

export function getRatioDimensions(
    dimensions: StrictDimensions,
    ratio: number | undefined
): StrictDimensions {
    if (!ratio) {
        return dimensions;
    }

    const {width, height} = dimensions;

    if (width * ratio > height) {
        return {
            width: height / ratio,
            height: height,
        };
    }

    return {
        width: width,
        height: width * ratio,
    };
}
