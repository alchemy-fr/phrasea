/**
 * A square thumbnail of the image, cropped around its center: small enough to
 * be displayed again instantly, unlike the original which the browser decodes
 * again each time it is shown. Fails when the browser cannot decode the
 * format (PSD, HEIC, TIFF… depending on the browser).
 */
export async function createThumbnail(file: File, size: number): Promise<Blob> {
    const src = URL.createObjectURL(file);
    try {
        const img = new Image();
        img.src = src;
        await img.decode();
        const {naturalWidth: width, naturalHeight: height} = img;
        if (!width || !height) {
            throw new Error(`${file.name} has no intrinsic size`);
        }
        const side = Math.min(width, height);
        const out = Math.min(size, side);
        const canvas = document.createElement('canvas');
        canvas.width = out;
        canvas.height = out;
        canvas
            .getContext('2d')!
            .drawImage(
                img,
                (width - side) / 2,
                (height - side) / 2,
                side,
                side,
                0,
                0,
                out,
                out
            );

        return await new Promise<Blob>((resolve, reject) =>
            canvas.toBlob(
                blob =>
                    blob
                        ? resolve(blob)
                        : reject(new Error(`No thumbnail for ${file.name}`)),
                'image/webp',
                0.85
            )
        );
    } finally {
        URL.revokeObjectURL(src);
    }
}
