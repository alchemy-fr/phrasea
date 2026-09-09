'use client';

import {useTranslation} from 'react-i18next';
import {
    FileIcon,
    FileImageIcon,
    FileAudioIcon,
    FileVideoIcon,
    FileTextIcon,
    FolderIcon,
    LayersIcon,
    LockIcon,
    ShieldAlertIcon,
    UserIcon,
    BookOpenIcon,
} from 'lucide-react';
import type {
    AssetStatus,
    Collection,
    Privacy,
    Tag,
    User,
    Workspace,
} from '@/types/api';
import {Chip} from '@/components/ui/misc';
import {Tooltip} from '@/components/ui/overlays';
import {
    assetStatusLabels,
    privacyLabels,
} from '@/features/attributes/types/registry';
import {FileKind, getFileKind} from '@/lib/utils/mime';
import {cn} from '@/lib/utils/cn';
import {pickTranslation} from '@/lib/utils/locale';
import {AssetStatus as AssetStatusEnum} from '@/types/api';

type Size = 'xs' | 'sm' | 'md';

export function TagChip({
    tag,
    size = 'xs',
    onClick,
}: {
    tag: Tag;
    size?: Size;
    onClick?: () => void;
}) {
    return (
        <Chip
            color={tag.color ?? undefined}
            size={size}
            onClick={onClick}
            title={tag.displayName ?? tag.name}
        >
            {tag.displayName ?? tag.name}
        </Chip>
    );
}

export function ColorSwatch({
    color,
    className,
}: {
    color: string;
    className?: string;
}) {
    return (
        <Tooltip content={color}>
            <span
                className={cn(
                    'inline-block size-4 rounded-sm border align-middle',
                    className
                )}
                style={{backgroundColor: color}}
            />
        </Tooltip>
    );
}

export function EntityChip({
    entity,
    size = 'xs',
}: {
    entity: {
        value: string | null;
        emoji?: string;
        color?: string;
        status?: number;
        translations?: Record<string, string>;
    };
    size?: Size;
}) {
    const label = pickTranslation(entity.translations, entity.value ?? '');
    const pending = entity.status === 1;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1',
                pending && 'italic text-warning-foreground'
            )}
        >
            {entity.color ? (
                <span
                    className="inline-block size-2.5 rounded-full"
                    style={{backgroundColor: entity.color}}
                />
            ) : null}
            {entity.emoji ? <span>{entity.emoji}</span> : null}
            <span className={size === 'xs' ? 'text-xs' : undefined}>
                {label}
            </span>
        </span>
    );
}

export function UserChip({user, size = 'xs'}: {user: User; size?: Size}) {
    return (
        <Chip
            size={size}
            icon={<UserIcon className="size-3" />}
            className={cn(user.removed && 'line-through opacity-60')}
        >
            {user.username}
        </Chip>
    );
}

export function WorkspaceChip({
    workspace,
    size = 'xs',
    onClick,
}: {
    workspace: Workspace;
    size?: Size;
    onClick?: () => void;
}) {
    return (
        <Chip
            size={size}
            icon={<LayersIcon className="size-3" />}
            onClick={onClick}
        >
            {workspace.displayName ?? workspace.name}
        </Chip>
    );
}

export function CollectionChip({
    collection,
    size = 'xs',
    onClick,
    absolute,
}: {
    collection: Collection;
    size?: Size;
    onClick?: () => void;
    absolute?: boolean;
}) {
    const label = absolute
        ? (collection.absoluteDisplayName ??
          collection.absoluteName ??
          collection.displayName ??
          collection.name)
        : (collection.displayName ?? collection.name);

    return (
        <Chip
            size={size}
            icon={
                collection.storyAsset ? (
                    <BookOpenIcon className="size-3" />
                ) : (
                    <FolderIcon className="size-3" />
                )
            }
            onClick={onClick}
            className={cn(collection.deleted && 'line-through opacity-60')}
            title={collection.absoluteDisplayName ?? label}
        >
            {label}
        </Chip>
    );
}

export function PrivacyIcon({
    privacy,
    noAccess,
    className,
}: {
    privacy: Privacy;
    noAccess?: boolean;
    className?: string;
}) {
    const {t} = useTranslation();
    const label = noAccess
        ? t('privacy.no_access', 'No access')
        : privacyLabels(t)[privacy];

    return (
        <Tooltip content={label}>
            <LockIcon className={cn('size-3.5', className)} />
        </Tooltip>
    );
}

export function PrivacyChip({
    privacy,
    noAccess,
    size = 'xs',
}: {
    privacy: Privacy;
    noAccess?: boolean;
    size?: Size;
}) {
    const {t} = useTranslation();
    const label = noAccess
        ? t('privacy.no_access', 'No access')
        : privacyLabels(t)[privacy];

    return (
        <Chip size={size} icon={<LockIcon className="size-3" />}>
            {label}
        </Chip>
    );
}

export function AssetStatusChip({
    status,
    size = 'xs',
}: {
    status: AssetStatus;
    size?: Size;
}) {
    const {t} = useTranslation();
    const label = assetStatusLabels(t)[status];

    return (
        <Chip
            size={size}
            icon={
                status === AssetStatusEnum.Quarantined ? (
                    <ShieldAlertIcon className="size-3" />
                ) : undefined
            }
            className={cn(
                status === AssetStatusEnum.Quarantined &&
                    'bg-destructive/15 text-destructive',
                status === AssetStatusEnum.Pending &&
                    'bg-warning/20 text-warning-foreground'
            )}
        >
            {label}
        </Chip>
    );
}

export function FileKindIcon({
    mimeType,
    className,
}: {
    mimeType: string | undefined;
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

export function FileTypeChip({
    mimeType,
    extension,
}: {
    mimeType?: string;
    extension?: string;
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
