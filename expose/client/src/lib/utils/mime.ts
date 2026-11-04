export enum FileKind {
    Unknown = 'unknown',
    Document = 'document',
    Audio = 'audio',
    Video = 'video',
    Image = 'image',
}

export function getFileKind(mimeType: string | null | undefined): FileKind {
    if (!mimeType) {
        return FileKind.Unknown;
    }
    const [main] = mimeType.split(';')[0].trim().toLowerCase().split('/');
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
