import type {FormValues} from '@/features/form-builder/types';

/**
 * What was entered in the form of the last commit made through an upload link,
 * offered again for the next one. Kept by this browser only, per link: a link
 * is shared, its other holders must not see what was entered here.
 */
const STORAGE_PREFIX = 'upl.link_form.';

type KeyValueStorage = Pick<Storage, 'getItem' | 'setItem' | 'removeItem'>;

function getLocalStorage(): KeyValueStorage | null {
    try {
        return typeof window !== 'undefined' ? window.localStorage : null;
    } catch {
        return null;
    }
}

function storageKey(linkId: string): string {
    return `${STORAGE_PREFIX}${linkId}`;
}

/** Without the keys handled by the uploader itself (`__notify`…) */
function withoutExtraFields(values: FormValues): FormValues {
    return Object.fromEntries(
        Object.entries(values).filter(([k]) => !k.startsWith('__'))
    );
}

export function readLastFormData(
    linkId: string,
    storage: KeyValueStorage | null = getLocalStorage()
): FormValues | null {
    if (!storage) {
        return null;
    }
    try {
        const data: unknown = JSON.parse(
            storage.getItem(storageKey(linkId)) ?? 'null'
        );
        if (!data || typeof data !== 'object' || Array.isArray(data)) {
            return null;
        }
        const values = withoutExtraFields(data as FormValues);

        return Object.keys(values).length > 0 ? values : null;
    } catch {
        return null;
    }
}

export function saveLastFormData(
    linkId: string,
    values: FormValues,
    storage: KeyValueStorage | null = getLocalStorage()
): void {
    const entered = withoutExtraFields(values);
    if (Object.keys(entered).length === 0) {
        clearLastFormData(linkId, storage);

        return;
    }
    try {
        storage?.setItem(storageKey(linkId), JSON.stringify(entered));
    } catch {
        // Quota exceeded or storage disabled: reusing is a bonus
    }
}

export function clearLastFormData(
    linkId: string,
    storage: KeyValueStorage | null = getLocalStorage()
): void {
    try {
        storage?.removeItem(storageKey(linkId));
    } catch {
        // Storage disabled: nothing was kept
    }
}
