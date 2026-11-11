import {describe, expect, it} from 'vitest';
import type {UploadLink} from '@/types/api';
import {
    fromDateTimeLocal,
    initialFormValues,
    splitSize,
    toBytes,
    toDateTimeLocal,
    toInput,
} from './uploadLinkForm';

describe('initialFormValues', () => {
    it('makes a new link expire in a week', () => {
        const values = initialFormValues(
            undefined,
            new Date(2026, 0, 10, 9, 5)
        );

        expect(values.expiresAt).toBe('2026-01-17T09:05');
        expect(values.target).toBe('');
        expect(values.maxAssets).toBe('');
    });

    it('starts from the link being edited', () => {
        const values = initialFormValues({
            target: {id: 't1', name: 'T'},
            name: 'John',
            expiresAt: null,
            maxAssets: 3,
            maxTotalSize: 2 * 1024 ** 3,
        } as UploadLink);

        expect(values).toEqual({
            target: 't1',
            name: 'John',
            expiresAt: '',
            maxAssets: '3',
            maxTotalSize: '2',
            sizeUnit: 'GB',
        });
    });
});

describe('sizes', () => {
    it('splits a size into a value and a unit', () => {
        expect(splitSize(null)).toEqual({value: '', unit: 'GB'});
        expect(splitSize(3 * 1024 ** 3)).toEqual({value: '3', unit: 'GB'});
        expect(splitSize(512 * 1024 ** 2)).toEqual({value: '512', unit: 'MB'});
    });

    it('turns a typed size into bytes', () => {
        expect(toBytes('', 'MB')).toBeNull();
        expect(toBytes('1,5', 'GB')).toBe(1.5 * 1024 ** 3);
        expect(toBytes('100', 'MB')).toBe(100 * 1024 ** 2);
    });
});

describe('dates', () => {
    it('goes to and from a datetime-local value', () => {
        const date = new Date(2026, 4, 3, 14, 30);

        expect(toDateTimeLocal(date)).toBe('2026-05-03T14:30');
        expect(fromDateTimeLocal('2026-05-03T14:30')).toBe(date.toISOString());
        expect(fromDateTimeLocal('')).toBeNull();
    });
});

describe('toInput', () => {
    const values = {
        target: 't1',
        name: '  ',
        expiresAt: '',
        maxAssets: '5',
        maxTotalSize: '',
        sizeUnit: 'GB' as const,
    };

    it('sends the target on creation only', () => {
        expect(toInput(values, true)).toEqual({
            target: '/targets/t1',
            name: null,
            expiresAt: null,
            maxAssets: 5,
            maxTotalSize: null,
        });
        expect(toInput(values, false)).not.toHaveProperty('target');
    });
});
