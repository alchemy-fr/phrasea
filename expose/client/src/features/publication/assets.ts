import type {Asset} from '@/types/api';

/** The asset an URL segment addresses: by id, else by slug */
export function findAsset(
    assets: Asset[],
    key: string | undefined
): Asset | undefined {
    if (!key) {
        return undefined;
    }
    const decoded = decodeURIComponent(key);

    return (
        assets.find(a => a.id === decoded) ??
        assets.find(a => a.slug === decoded)
    );
}

/** The asset before / after, the first and last ones being neighbours */
export function cycle(
    assets: Asset[],
    current: Asset,
    direction: 1 | -1
): Asset {
    const i = assets.findIndex(a => a.id === current.id);
    const n = assets.length;

    return assets[(((i + direction) % n) + n) % n];
}
