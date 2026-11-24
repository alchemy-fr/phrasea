import {describe, expect, it} from 'vitest';
import {AttributeType} from '@/types/api';
import {getAttributeType} from './registry';

const date = getAttributeType(AttributeType.Date);
const dateTime = getAttributeType(AttributeType.DateTime);

describe('date attribute type', () => {
    it.each([
        ['2026-04-26', '2026-04-26'],
        ['2026-04-26T00:00:00+02:00', '2026-04-26'],
        ['2026-04-26T00:00:00.000Z', '2026-04-26'],
        ['2026-04-26T23:30:00-05:00', '2026-04-26'],
    ])('normalizes %s to the calendar day', (input, expected) => {
        expect(date.normalize!(input)).toBe(expected);
    });

    it.each([[''], [undefined], [null], ['not a date']])(
        'leaves %s unchanged',
        input => {
            expect(date.normalize!(input)).toBe(input);
        }
    );

    it('denormalizes an API value to the stored day', () => {
        expect(date.denormalize!('2026-04-26T00:00:00+00:00')).toBe(
            '2026-04-26'
        );
        expect(
            date.normalize!(date.denormalize!('2026-04-26T00:00:00+00:00'))
        ).toBe('2026-04-26');
    });
});

describe('date_time attribute type', () => {
    it.each([
        ['2026-04-26T10:00:00'],
        ['2026-04-26T10:00'],
        ['2026-04-26T10:00:00.123Z'],
        ['2026-04-26T10:00:00+02:00'],
    ])('normalizes %s to UTC without milliseconds', input => {
        const normalized = dateTime.normalize!(input);

        expect(normalized).not.toContain('.');
        expect(normalized).toBe(
            new Date(input).toISOString().replace(/\.\d{3}Z$/, 'Z')
        );
    });

    it.each([[''], [undefined], [null], ['not a date']])(
        'leaves %s unchanged',
        input => {
            expect(dateTime.normalize!(input)).toBe(input);
        }
    );

    it('denormalizes to a datetime-local input value', () => {
        expect(dateTime.denormalize!('2026-04-26T10:00:00+00:00')).toMatch(
            /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/
        );
    });

    it('keeps the same instant on a round trip', () => {
        expect(
            dateTime.normalize!(
                dateTime.denormalize!('2026-04-26T10:00:00+00:00')
            )
        ).toBe('2026-04-26T10:00:00Z');
        expect(
            dateTime.normalize!(
                dateTime.denormalize!('2026-04-26T10:00:00+02:00')
            )
        ).toBe('2026-04-26T08:00:00Z');
    });
});
