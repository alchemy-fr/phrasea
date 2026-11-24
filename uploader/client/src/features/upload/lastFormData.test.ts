import {describe, expect, it} from 'vitest';
import {
    clearLastFormData,
    readLastFormData,
    saveLastFormData,
} from './lastFormData';

function memoryStorage() {
    const map = new Map<string, string>();

    return {
        map,
        getItem: (k: string) => map.get(k) ?? null,
        setItem: (k: string, v: string) => void map.set(k, v),
        removeItem: (k: string) => void map.delete(k),
    };
}

describe('lastFormData', () => {
    it('reads back what was entered with the same link only', () => {
        const storage = memoryStorage();
        saveLastFormData('link-a', {title: 'My pictures'}, storage);

        expect(readLastFormData('link-a', storage)).toEqual({
            title: 'My pictures',
        });
        expect(readLastFormData('link-b', storage)).toBeNull();
    });

    it('leaves out the keys handled by the uploader', () => {
        const storage = memoryStorage();
        saveLastFormData('l', {title: 'T', __notify: true}, storage);
        expect(readLastFormData('l', storage)).toEqual({title: 'T'});

        saveLastFormData('l', {__notify: true}, storage);
        expect(readLastFormData('l', storage)).toBeNull();
        expect(storage.map.size).toBe(0);
    });

    it('forgets what was entered', () => {
        const storage = memoryStorage();
        saveLastFormData('l', {title: 'T'}, storage);
        clearLastFormData('l', storage);

        expect(readLastFormData('l', storage)).toBeNull();
    });

    it('ignores what it cannot read', () => {
        const storage = memoryStorage();
        storage.setItem('upl.link_form.l', '{oops');
        expect(readLastFormData('l', storage)).toBeNull();

        storage.setItem('upl.link_form.l', '["a"]');
        expect(readLastFormData('l', storage)).toBeNull();
    });

    it('works without a usable storage', () => {
        const failing = {
            getItem: () => {
                throw new Error('denied');
            },
            setItem: () => {
                throw new Error('denied');
            },
            removeItem: () => {
                throw new Error('denied');
            },
        };

        expect(() =>
            saveLastFormData('l', {title: 'T'}, failing)
        ).not.toThrow();
        expect(() => clearLastFormData('l', failing)).not.toThrow();
        expect(readLastFormData('l', failing)).toBeNull();
        expect(readLastFormData('l', null)).toBeNull();
    });
});
