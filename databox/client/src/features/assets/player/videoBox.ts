export type Box = {
    left: number;
    top: number;
    width: number;
    height: number;
};

/**
 * The part of an element showing a media of that size with
 * `object-fit: contain` (centered, letterboxed), in the coordinates of the
 * element's box; the whole box while the size of the media is unknown.
 */
export function containBox(
    element: Box,
    mediaWidth: number,
    mediaHeight: number
): Box {
    if (
        mediaWidth <= 0 ||
        mediaHeight <= 0 ||
        element.width <= 0 ||
        element.height <= 0
    ) {
        return element;
    }
    const ratio = mediaWidth / mediaHeight;
    let width = element.width;
    let height = width / ratio;
    if (height > element.height) {
        height = element.height;
        width = height * ratio;
    }

    return {
        left: element.left + (element.width - width) / 2,
        top: element.top + (element.height - height) / 2,
        width,
        height,
    };
}
