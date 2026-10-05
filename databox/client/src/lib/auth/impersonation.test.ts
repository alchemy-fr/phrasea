import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {
    getImpersonation,
    IMPERSONATION_STORAGE_KEY,
    onImpersonationChangedElsewhere,
    saveImpersonation,
    startImpersonation,
    stopImpersonation,
    type ImpersonatedUser,
} from './impersonation';

// Node ships its own (broken, file-backed) localStorage that shadows jsdom's
const storage = new Map<string, string>();
Object.defineProperty(window, 'localStorage', {
    configurable: true,
    value: {
        getItem: (k: string) => storage.get(k) ?? null,
        setItem: (k: string, v: string) => storage.set(k, v),
        removeItem: (k: string) => storage.delete(k),
        clear: () => storage.clear(),
    },
});

const alice: ImpersonatedUser = {
    id: 'alice-id',
    username: 'alice',
    roles: ['databox'],
    groups: ['g1'],
};

describe('impersonation', () => {
    const reload = vi.fn();
    const originalLocation = window.location;

    beforeEach(() => {
        window.localStorage.clear();
        sessionStorage.clear();
        reload.mockClear();
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: {...originalLocation, reload},
        });
    });

    afterEach(() => {
        Object.defineProperty(window, 'location', {
            configurable: true,
            value: originalLocation,
        });
    });

    it('persists the impersonated user', () => {
        expect(getImpersonation()).toBeUndefined();
        saveImpersonation(alice);
        expect(getImpersonation()).toEqual(alice);
    });

    it('ignores a malformed stored value', () => {
        window.localStorage.setItem(IMPERSONATION_STORAGE_KEY, '{"id": 42}');
        expect(getImpersonation()).toBeUndefined();
        window.localStorage.setItem(IMPERSONATION_STORAGE_KEY, 'not json');
        expect(getImpersonation()).toBeUndefined();
    });

    it('drops the previous user caches and reloads on switch', () => {
        sessionStorage.setItem('dbx.prefs', '{}');
        startImpersonation(alice);

        expect(getImpersonation()).toEqual(alice);
        expect(sessionStorage.getItem('dbx.prefs')).toBeNull();
        expect(reload).toHaveBeenCalledOnce();

        sessionStorage.setItem('dbx.prefs', '{}');
        stopImpersonation();

        expect(getImpersonation()).toBeUndefined();
        expect(sessionStorage.getItem('dbx.prefs')).toBeNull();
        expect(reload).toHaveBeenCalledTimes(2);
    });

    it('only reacts to another tab switching to a different user', () => {
        const callback = vi.fn();
        const unsubscribe = onImpersonationChangedElsewhere(callback);
        const fire = (oldValue: unknown, newValue: unknown) =>
            window.dispatchEvent(
                new StorageEvent('storage', {
                    key: IMPERSONATION_STORAGE_KEY,
                    oldValue: oldValue ? JSON.stringify(oldValue) : null,
                    newValue: newValue ? JSON.stringify(newValue) : null,
                })
            );

        // Identity refresh of the same user
        fire(alice, {...alice, roles: ['databox', 'tech']});
        expect(callback).not.toHaveBeenCalled();

        fire(null, alice);
        fire(alice, null);
        expect(callback).toHaveBeenCalledTimes(2);

        unsubscribe();
        fire(null, alice);
        expect(callback).toHaveBeenCalledTimes(2);
    });
});
