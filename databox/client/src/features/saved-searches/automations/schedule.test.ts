import {describe, expect, it} from 'vitest';
import {
    changePresetKind,
    cronToPreset,
    parseTime,
    presetToCron,
    SchedulePreset,
} from './schedule';

describe('schedule presets', () => {
    it.each<[SchedulePreset, string | null]>([
        [{kind: 'manual'}, null],
        [{kind: 'hourly', minute: 15}, '15 * * * *'],
        [{kind: 'daily', hour: 8, minute: 30}, '30 8 * * *'],
        [{kind: 'weekly', weekday: 1, hour: 18, minute: 0}, '0 18 * * 1'],
        [{kind: 'custom', expression: ' */10   * * * * '}, '*/10 * * * *'],
    ])('converts %j to cron', (preset, expected) => {
        expect(presetToCron(preset)).toBe(expected);
    });

    it.each<[string | null | undefined, SchedulePreset]>([
        [null, {kind: 'manual'}],
        ['', {kind: 'manual'}],
        ['15 * * * *', {kind: 'hourly', minute: 15}],
        ['30 8 * * *', {kind: 'daily', hour: 8, minute: 30}],
        ['0 18 * * 1', {kind: 'weekly', weekday: 1, hour: 18, minute: 0}],
        ['*/10 * * * *', {kind: 'custom', expression: '*/10 * * * *'}],
        ['0 8 * * 1-5', {kind: 'custom', expression: '0 8 * * 1-5'}],
        ['75 * * * *', {kind: 'custom', expression: '75 * * * *'}],
        ['0 25 * * *', {kind: 'custom', expression: '0 25 * * *'}],
    ])('recognizes %j', (expression, expected) => {
        expect(cronToPreset(expression)).toEqual(expected);
    });

    it('round-trips the presets', () => {
        const presets: SchedulePreset[] = [
            {kind: 'hourly', minute: 0},
            {kind: 'daily', hour: 23, minute: 59},
            {kind: 'weekly', weekday: 0, hour: 0, minute: 5},
        ];
        for (const preset of presets) {
            expect(cronToPreset(presetToCron(preset))).toEqual(preset);
        }
    });

    it('keeps the time when switching kind', () => {
        const daily: SchedulePreset = {kind: 'daily', hour: 9, minute: 45};
        expect(changePresetKind(daily, 'weekly')).toEqual({
            kind: 'weekly',
            weekday: 1,
            hour: 9,
            minute: 45,
        });
        expect(changePresetKind(daily, 'hourly')).toEqual({
            kind: 'hourly',
            minute: 45,
        });
        expect(changePresetKind(daily, 'custom')).toEqual({
            kind: 'custom',
            expression: '45 9 * * *',
        });
    });

    it('parses times', () => {
        expect(parseTime('08:30')).toEqual({hour: 8, minute: 30});
        expect(parseTime('8:05')).toEqual({hour: 8, minute: 5});
        expect(parseTime('24:00')).toBeUndefined();
        expect(parseTime('12:60')).toBeUndefined();
        expect(parseTime('noon')).toBeUndefined();
    });
});
