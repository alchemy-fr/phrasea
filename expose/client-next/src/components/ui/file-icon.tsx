import {
    FileAudioIcon,
    FileIcon,
    FileImageIcon,
    FileTextIcon,
    FileVideoIcon,
} from 'lucide-react';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {cn} from '@/lib/utils/cn';

/** Icon of the kind of a file (image, video, audio, document…) */
export function FileKindIcon({
    mimeType,
    className,
}: {
    mimeType: string | null | undefined;
    className?: string;
}) {
    const cls = cn('size-10 text-muted-foreground', className);
    switch (getFileKind(mimeType)) {
        case FileKind.Image:
            return <FileImageIcon className={cls} />;
        case FileKind.Audio:
            return <FileAudioIcon className={cls} />;
        case FileKind.Video:
            return <FileVideoIcon className={cls} />;
        case FileKind.Document:
            return <FileTextIcon className={cls} />;
        default:
            return <FileIcon className={cls} />;
    }
}
