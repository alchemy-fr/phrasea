/**
 * Turns a canvas `data:` URL (photo editor export) into a `File` ready to be
 * uploaded.
 */
export function dataUrlToFile(dataUrl: string, filename: string): File {
    const [header, base64] = dataUrl.split(',');
    const type = header.match(/^data:([^;]+);base64$/)?.[1];
    if (!type || !base64) {
        throw new Error('Unsupported data URL');
    }
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return new File([bytes], filename, {type});
}
