export enum FileKind {
    Unknown = 'unknown',
    Document = 'document',
    Audio = 'audio',
    Video = 'video',
    Image = 'image',
}

export function getFileKind(mimeType: string | undefined): FileKind {
    if (!mimeType) {
        return FileKind.Unknown;
    }
    const [main] = mimeType.split('/');
    switch (main) {
        case 'image':
            return FileKind.Image;
        case 'video':
            return FileKind.Video;
        case 'audio':
            return FileKind.Audio;
        case 'application':
        case 'text':
            return FileKind.Document;
        default:
            return FileKind.Unknown;
    }
}

const extensionToMime: Record<string, string> = {
    heic: 'image/heic',
    heif: 'image/heif',
    psd: 'image/vnd.adobe.photoshop',
    psb: 'image/vnd.adobe.photoshop',
    indd: 'application/x-indesign',
    svg: 'image/svg+xml',
    webp: 'image/webp',
    mkv: 'video/x-matroska',
    mov: 'video/quicktime',
    avi: 'video/x-msvideo',
    m4a: 'audio/mp4',
    aiff: 'audio/aiff',
    vtt: 'text/vtt',
    json: 'application/json',
    csv: 'text/csv',
    pdf: 'application/pdf',
};

/**
 * Browsers leave `File.type` empty for many professional formats; fall back
 * to the extension so the API can still validate the upload.
 */
export function getMimeTypeFromFile(file: File): string | undefined {
    if (file.type) {
        return file.type;
    }
    const ext = file.name.split('.').pop()?.toLowerCase();

    return ext ? extensionToMime[ext] : undefined;
}

/**
 * Converts the server `allowedTypes` map into the react-dropzone `accept`
 * format (`{mime: [ext...]}`), keeping wildcard MIME keys.
 */
export function toDropzoneAccept(
    allowed: Record<string, string[]>
): Record<string, string[]> | undefined {
    const keys = Object.keys(allowed);
    if (keys.length === 0 || allowed['*/*']) {
        return undefined;
    }

    return allowed;
}
