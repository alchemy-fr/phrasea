import {
    FileAudioIcon,
    FileIcon,
    FileImageIcon,
    FileTextIcon,
    FileVideoIcon,
    type LucideIcon,
} from 'lucide-react';
import {cn} from '../utils/cn';
import {FileFamily, getFileFamily} from '../utils/mime';

const familyIcons: Record<FileFamily, LucideIcon> = {
    [FileFamily.Image]: FileImageIcon,
    [FileFamily.Audio]: FileAudioIcon,
    [FileFamily.Video]: FileVideoIcon,
    [FileFamily.Document]: FileTextIcon,
    [FileFamily.Other]: FileIcon,
};

/** Icon of the family of a file (image, video, audio, document…) */
export function FileFamilyIcon({
    mimeType,
    className,
}: {
    mimeType: string | null | undefined;
    className?: string;
}) {
    const family = getFileFamily(mimeType);
    const Icon = familyIcons[family];

    return (
        <Icon
            data-testid="file-family-icon"
            data-family={family}
            className={cn('size-10 text-muted-foreground', className)}
        />
    );
}

/** Extension of a file (or the subtype of its MIME type) on a dark badge */
export function FileTypeChip({
    mimeType,
    extension,
}: {
    mimeType?: string | null;
    extension?: string | null;
}) {
    const label =
        extension?.toUpperCase() ?? mimeType?.split('/')[1]?.toUpperCase();
    if (!label) {
        return null;
    }

    return (
        <span className="rounded bg-black/60 px-1 py-0.5 font-mono text-[10px] font-semibold text-white uppercase">
            {label}
        </span>
    );
}
