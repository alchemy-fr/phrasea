import {MemoryStorage} from '@alchemy/storage';

const cache = new MemoryStorage();

export function clearApiCache(): void {
    cache.clear();
}

export async function wrapCached<T>(
    key: string,
    expires: number,
    handler: () => Promise<T>
): Promise<T> {
    const cachedItem = getApiCacheItem<T>(key);
    if (cachedItem) {
        return cachedItem;
    }

    const r = await handler();
    setApiCacheItem<T>(key, r, expires);

    return r;
}

export function setApiCacheItem<T>(key: string, value: T, expires?: number) {
    cache.setItem(key, JSON.stringify(value), {expires});
}

export function getApiCacheItem<T>(key: string): T | null {
    const item = cache.getItem(key);
    if (item) {
        return JSON.parse(item) as T;
    }

    return null;
}

export function removeApiCacheItem(key: string) {
    cache.removeItem(key);
}
