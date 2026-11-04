import {describe, expect, it} from 'vitest';
import {getFileKey, RESUME_TTL_MS, ResumeStorage} from './resumeStorage';

function memoryStorage() {
    const map = new Map<string, string>();

    return {
        map,
        getItem: (k: string) => map.get(k) ?? null,
        setItem: (k: string, v: string) => void map.set(k, v),
        removeItem: (k: string) => void map.delete(k),
    };
}

describe('getFileKey', () => {
    it('identifies a file by size, date and name', () => {
        const file = new File(['abc'], 'a.jpg', {lastModified: 42});
        expect(getFileKey(file)).toBe('3:42:a.jpg');
    });
});

describe('ResumeStorage', () => {
    it('reads back what was written by an earlier page', () => {
        const storage = memoryStorage();
        const first = new ResumeStorage('alice', storage);
        first.start('k', 'u1');
        first.addPart('k', 'u1', {ETag: 'e1', PartNumber: 1});
        first.addPart('k', 'u1', {ETag: 'e2', PartNumber: 2});

        // A new instance, as after a reload
        const second = new ResumeStorage('alice', storage);
        expect(second.get('k')).toMatchObject({
            uploadId: 'u1',
            parts: [
                {ETag: 'e1', PartNumber: 1},
                {ETag: 'e2', PartNumber: 2},
            ],
        });
    });

    it('keeps the entries of each user apart', () => {
        const storage = memoryStorage();
        new ResumeStorage('alice', storage).start('k', 'u1');
        new ResumeStorage('alice', storage).addPart('k', 'u1', {
            ETag: 'e',
            PartNumber: 1,
        });
        expect(new ResumeStorage('bob', storage).get('k')).toBeUndefined();
    });

    it('has nothing to resume before a part is sent', () => {
        const s = new ResumeStorage('alice', memoryStorage());
        s.start('k', 'u1');
        expect(s.get('k')).toBeUndefined();
    });

    it('ignores the parts of another upload of the same file', () => {
        const s = new ResumeStorage('alice', memoryStorage());
        s.start('k', 'u2');
        s.addPart('k', 'u1', {ETag: 'e', PartNumber: 1});
        expect(s.get('k')).toBeUndefined();
    });

    it('forgets an entry once cleared', () => {
        const storage = memoryStorage();
        const s = new ResumeStorage('alice', storage);
        s.start('k', 'u1');
        s.addPart('k', 'u1', {ETag: 'e', PartNumber: 1});
        s.clear('k');
        expect(s.get('k')).toBeUndefined();
        expect(storage.map.size).toBe(0);
    });

    it('forgets stale entries', () => {
        let now = 1000;
        const storage = memoryStorage();
        const s = new ResumeStorage('alice', storage, () => now);
        s.start('k', 'u1');
        s.addPart('k', 'u1', {ETag: 'e', PartNumber: 1});
        now += RESUME_TTL_MS + 1;
        expect(s.get('k')).toBeUndefined();
    });

    it('survives a corrupted storage', () => {
        const storage = memoryStorage();
        storage.setItem('upl.uploads.alice', '{not json');
        expect(new ResumeStorage('alice', storage).get('k')).toBeUndefined();
    });
});
