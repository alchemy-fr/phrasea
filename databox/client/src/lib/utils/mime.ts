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

/** Values of the `@family` built-in attribute (mirrors the API's `FileFamilyEnum`) */
export enum FileFamily {
    Image = 'image',
    Audio = 'audio',
    Video = 'video',
    Document = 'document',
    Other = 'other',
}

export const fileFamilies: FileFamily[] = [
    FileFamily.Image,
    FileFamily.Audio,
    FileFamily.Video,
    FileFamily.Document,
    FileFamily.Other,
];

const documentTypes = new Set([
    'application/pdf',
    'application/rtf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'application/vnd.oasis.opendocument.text',
    'application/vnd.oasis.opendocument.spreadsheet',
    'application/vnd.oasis.opendocument.presentation',
    'application/epub+zip',
]);
const videoTypes = new Set(['application/mxf', 'application/ogg']);
const imageTypes = new Set([
    'application/postscript',
    'application/x-photoshop',
    'application/photoshop',
    'application/psd',
    'application/vnd.3gpp.pic-bw-small',
    'application/illustrator',
]);

/**
 * Family of a file from its MIME type; same rules as the API's
 * `FileFamilyEnum::fromMimeType()` (used to index `@family`).
 */
export function getFileFamily(mimeType: string | undefined): FileFamily {
    if (!mimeType) {
        return FileFamily.Other;
    }
    const type = mimeType.split(';')[0].trim().toLowerCase();
    if (type.startsWith('image/')) {
        return FileFamily.Image;
    }
    if (type.startsWith('audio/')) {
        return FileFamily.Audio;
    }
    if (type.startsWith('video/')) {
        return FileFamily.Video;
    }
    if (type.startsWith('text/') || documentTypes.has(type)) {
        return FileFamily.Document;
    }
    if (videoTypes.has(type)) {
        return FileFamily.Video;
    }
    if (imageTypes.has(type)) {
        return FileFamily.Image;
    }

    return FileFamily.Other;
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
