import {describe, expect, it} from 'vitest';
import {fromLocalInput, toLocalInput} from './dates';

describe('dates', () => {
    it('round-trips a date through the input', () => {
        const iso = fromLocalInput('2026-03-04T05:06');
        expect(iso).not.toBeNull();
        expect(toLocalInput(iso)).toBe('2026-03-04T05:06');
    });

    it('is empty for nothing', () => {
        expect(toLocalInput(null)).toBe('');
        expect(toLocalInput('nope')).toBe('');
        expect(fromLocalInput('')).toBeNull();
    });
});
