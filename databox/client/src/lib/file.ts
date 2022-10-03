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

export function dataURLtoFile(dataurl: string, filename: string): File {
    const arr = dataurl.split(',');
    const mime = arr[0].match(/:(.*?);/)![1];
    const s = atob(arr[1]);
    let n = s.length;
    const u8arr = new Uint8Array(n);
    while (n) {
        u8arr[n - 1] = s.charCodeAt(n - 1);
        --n;
    }
    return new File([u8arr], filename, { type: mime });
}
