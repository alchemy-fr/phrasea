import type {ApiFile, Asset, Share} from '@/types/api';

/**
 * A rendition of a shared asset the visitor can view or download.
 *
 * Several renditions of an asset may point to the same file (a preview
 * picking the source, …): they are told apart by `id`, the rendition's —
 * never by the file or its URL.
 */
export type ShareRendition = {
    id: string;
    /** Name of the rendition definition, to match renditions across assets */
    name: string;
    label: string;
    url: string;
    type?: string;
    size?: number;
};

/**
 * The renditions of a shared asset: the ones the share exposes, or — shares
 * served by an older API, without them — the renditions embedded in the asset.
 */
export function getShareRenditions(
    share: Share,
    asset: Asset,
    fallbackLabels: {preview: string; main: string; source: string}
): ShareRendition[] {
    const single = share.assets.length === 1;
    const list: ShareRendition[] = (share.alternateUrls ?? [])
        // Without `assetId`, the URL can only be of the first (only) asset
        .filter(a => (a.assetId ? a.assetId === asset.id : single))
        .map(a => ({
            id: a.id ?? a.definitionId ?? `${asset.id}:${a.name}`,
            name: a.name,
            label: a.displayName || a.name,
            url: a.url,
            type: a.type ?? undefined,
            size: a.size ?? undefined,
        }));
    if (list.length > 0) {
        return list;
    }

    const embedded: [keyof typeof fallbackLabels, ApiFile | undefined][] = [
        ['preview', asset.preview?.file],
        ['main', asset.main?.file],
        ['source', asset.source],
    ];

    return embedded
        .filter((e): e is [keyof typeof fallbackLabels, ApiFile] => !!e[1]?.url)
        .map(([key, file]) => ({
            id: `${asset.id}:${key}`,
            name: key,
            label: fallbackLabels[key],
            url: file.url!,
            type: file.type || undefined,
            size: file.size || undefined,
        }));
}

/**
 * The rendition displayed first: the one of the asset's preview, else the
 * first one the player can render.
 */
export function getDefaultViewRendition(
    asset: Asset,
    renditions: ShareRendition[]
): ShareRendition | undefined {
    const previewName = asset.preview?.name;

    return (
        (previewName && renditions.find(r => r.name === previewName)) ||
        renditions.find(r => r.name === 'preview') ||
        renditions.find(r => isViewable(r.type)) ||
        renditions[0]
    );
}

function isViewable(type: string | undefined): boolean {
    return !!type && /^(image|video|audio)\/|^application\/pdf$/.test(type);
}

/**
 * The file played for a rendition: the file embedded in the asset when it is
 * the same rendition (direct URL, full metadata), else the share's URL of the
 * rendition (redirects to the file).
 */
export function getRenditionFile(
    asset: Asset,
    rendition: ShareRendition
): ApiFile {
    for (const r of [asset.preview, asset.main]) {
        if (r?.file?.url && r.name && r.name === rendition.name) {
            return r.file;
        }
    }

    return {
        id: rendition.id,
        url: rendition.url,
        type: rendition.type ?? '',
        extension: extensionFromMimeType(rendition.type) ?? '',
        alternateUrls: [],
        size: rendition.size ?? 0,
        docUniqueId: '',
        checksum: '',
        fileName: rendition.label,
        analysisPending: false,
    };
}

const extensionOverrides: Record<string, string> = {
    'image/jpeg': 'jpg',
    'image/svg+xml': 'svg',
    'image/tiff': 'tif',
    'audio/mpeg': 'mp3',
    'video/quicktime': 'mov',
    'video/x-matroska': 'mkv',
    'video/x-msvideo': 'avi',
    'text/plain': 'txt',
    'application/msword': 'doc',
    'application/vnd.ms-excel': 'xls',
    'application/vnd.ms-powerpoint': 'ppt',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
        'docx',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': 'xlsx',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation':
        'pptx',
};

/** Usual extension of a MIME type (`image/png` → `png`), when it has one */
export function extensionFromMimeType(
    type: string | undefined
): string | undefined {
    if (!type) {
        return undefined;
    }
    const mime = type.split(';')[0].trim().toLowerCase();
    if (extensionOverrides[mime]) {
        return extensionOverrides[mime];
    }
    const subtype = mime.split('/')[1]?.replace(/^x-/, '');

    return subtype && /^[a-z0-9]{1,5}$/.test(subtype) ? subtype : undefined;
}

/**
 * Name of the downloaded file: the asset name (without its own extension),
 * the rendition, and the extension of the rendition's type.
 */
export function getDownloadFileName(
    assetName: string | undefined,
    rendition: ShareRendition
): string {
    const base =
        (assetName ?? '')
            .replace(/\.[a-z0-9]{1,5}$/i, '')
            .replace(/[\\/:*?"<>|]+/g, '_')
            .trim() || 'asset';
    const ext = extensionFromMimeType(rendition.type);

    return `${base} - ${rendition.label}${ext ? `.${ext}` : ''}`;
}
