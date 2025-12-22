import {FileTypeEnum} from './types';

export function getFileTypeFromMIMEType(
    type: string | undefined
): FileTypeEnum {
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

    if (
        [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ].includes(type)
    ) {
        return FileTypeEnum.Document;
    }

    return FileTypeEnum.Unknown;
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
    return new File([u8arr], filename, {type: mime});
}

export function validateUrl(value: string): boolean {
    try {
        new URL(value);
        return true;
    } catch {
        return false;
    }
}
