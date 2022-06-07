export enum FileTypeEnum {
    Unknown,
    Document,
    Audio,
    Video,
    Image,
}

export function getFileTypeFromMIMEType(type: string | undefined): FileTypeEnum {
    if (!type) {
        return FileTypeEnum.Unknown;
    }

    if (type.match(/^image\//)) {
        return FileTypeEnum.Image;
    }
    if (type.match(/^video\//)) {
        return FileTypeEnum.Video;
    }
    if (type.match(/^audio\//)) {
        return FileTypeEnum.Audio;
    }

    return FileTypeEnum.Document;
}
