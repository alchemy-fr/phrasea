/** Main type of a file, deciding how it is played */
export enum FileKind {
    Unknown = 'unknown',
    Document = 'document',
    Audio = 'audio',
    Video = 'video',
    Image = 'image',
}

function normalizeMimeType(mimeType: string): string {
    return mimeType.split(';')[0].trim().toLowerCase();
}

export function getFileKind(mimeType: string | null | undefined): FileKind {
    if (!mimeType) {
        return FileKind.Unknown;
    }
    const [main] = normalizeMimeType(mimeType).split('/');
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

/** Values of the databox `@family` built-in attribute (mirrors the API's `FileFamilyEnum`) */
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
 * Family of a file from its MIME type; same rules as the databox API's
 * `FileFamilyEnum::fromMimeType()` (used to index `@family`).
 */
export function getFileFamily(mimeType: string | null | undefined): FileFamily {
    if (!mimeType) {
        return FileFamily.Other;
    }
    const type = normalizeMimeType(mimeType);
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
    hevc: 'video/hevc',
    m4a: 'audio/mp4',
    aac: 'audio/aac',
    aiff: 'audio/aiff',
    wav: 'audio/wav',
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
 * format (`{mime: [ext...]}`), keeping wildcard MIME keys. Undefined when
 * every type is allowed.
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
 * The allowed types as shown to users: their extensions, or the MIME type
 * when it has none. Undefined when every type is allowed.
 */
export function describeAllowedTypes(
    allowed: Record<string, string[]>
): string | undefined {
    const accept = toDropzoneAccept(allowed);
    if (!accept) {
        return undefined;
    }
    const labels = Object.entries(accept).flatMap(([mime, extensions]) =>
        extensions.length > 0
            ? extensions.map(e => e.replace(/^\./, '').toUpperCase())
            : [mime]
    );

    return [...new Set(labels)].join(', ');
}
