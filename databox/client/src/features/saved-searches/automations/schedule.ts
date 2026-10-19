import type {TFunction} from 'i18next';

/**
 * Schedules offered as presets; anything else is kept as a custom cron expression.
 * Week days follow cron: 0 = Sunday … 6 = Saturday.
 */
export type SchedulePreset =
    | {kind: 'manual'}
    | {kind: 'hourly'; minute: number}
    | {kind: 'daily'; hour: number; minute: number}
    | {kind: 'weekly'; weekday: number; hour: number; minute: number}
    | {kind: 'custom'; expression: string};

export type SchedulePresetKind = SchedulePreset['kind'];

const int = (v: string) => parseInt(v, 10);

function inRange(v: number, min: number, max: number): boolean {
    return Number.isInteger(v) && v >= min && v <= max;
}

/**
 * The cron expression of a preset, null for an automation run on demand only.
 */
export function presetToCron(preset: SchedulePreset): string | null {
    switch (preset.kind) {
        case 'manual':
            return null;
        case 'hourly':
            return `${preset.minute} * * * *`;
        case 'daily':
            return `${preset.minute} ${preset.hour} * * *`;
        case 'weekly':
            return `${preset.minute} ${preset.hour} * * ${preset.weekday}`;
        case 'custom':
            return preset.expression.trim().replace(/\s+/g, ' ');
    }
}

/**
 * Recognizes the expressions written by presetToCron().
 */
export function cronToPreset(expression?: string | null): SchedulePreset {
    const expr = expression?.trim().replace(/\s+/g, ' ');
    if (!expr) {
        return {kind: 'manual'};
    }

    let m = /^(\d{1,2}) \* \* \* \*$/.exec(expr);
    if (m && inRange(int(m[1]), 0, 59)) {
        return {kind: 'hourly', minute: int(m[1])};
    }

    m = /^(\d{1,2}) (\d{1,2}) \* \* \*$/.exec(expr);
    if (m && inRange(int(m[1]), 0, 59) && inRange(int(m[2]), 0, 23)) {
        return {kind: 'daily', minute: int(m[1]), hour: int(m[2])};
    }

    m = /^(\d{1,2}) (\d{1,2}) \* \* ([0-6])$/.exec(expr);
    if (m && inRange(int(m[1]), 0, 59) && inRange(int(m[2]), 0, 23)) {
        return {
            kind: 'weekly',
            minute: int(m[1]),
            hour: int(m[2]),
            weekday: int(m[3]),
        };
    }

    return {kind: 'custom', expression: expr};
}

/**
 * Switches to another kind of preset, keeping the time already chosen.
 */
export function changePresetKind(
    preset: SchedulePreset,
    kind: SchedulePresetKind
): SchedulePreset {
    const minute = 'minute' in preset ? preset.minute : 0;
    const hour = 'hour' in preset ? preset.hour : 8;
    const weekday = 'weekday' in preset ? preset.weekday : 1;

    switch (kind) {
        case 'manual':
            return {kind};
        case 'hourly':
            return {kind, minute};
        case 'daily':
            return {kind, hour, minute};
        case 'weekly':
            return {kind, weekday, hour, minute};
        case 'custom':
            return {kind, expression: presetToCron(preset) ?? '0 * * * *'};
    }
}

/** "HH:MM" */
export function formatTime(hour: number, minute: number): string {
    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`;
}

/** Parses "HH:MM", undefined when invalid */
export function parseTime(
    value: string
): {hour: number; minute: number} | undefined {
    const m = /^(\d{1,2}):(\d{2})$/.exec(value.trim());
    if (!m || !inRange(int(m[1]), 0, 23) || !inRange(int(m[2]), 0, 59)) {
        return undefined;
    }

    return {hour: int(m[1]), minute: int(m[2])};
}

export function weekdayLabels(t: TFunction): string[] {
    return [
        t('automation.schedule.weekday.0', 'Sunday'),
        t('automation.schedule.weekday.1', 'Monday'),
        t('automation.schedule.weekday.2', 'Tuesday'),
        t('automation.schedule.weekday.3', 'Wednesday'),
        t('automation.schedule.weekday.4', 'Thursday'),
        t('automation.schedule.weekday.5', 'Friday'),
        t('automation.schedule.weekday.6', 'Saturday'),
    ];
}

/**
 * Human readable schedule.
 */
export function describeSchedule(
    expression: string | null | undefined,
    t: TFunction
): string {
    const preset = cronToPreset(expression);
    switch (preset.kind) {
        case 'manual':
            return t('automation.schedule.manual', 'On demand only');
        case 'hourly':
            return t(
                'automation.schedule.hourly_at',
                'Every hour at minute {{minute}}',
                {minute: preset.minute}
            );
        case 'daily':
            return t('automation.schedule.daily_at', 'Every day at {{time}}', {
                time: formatTime(preset.hour, preset.minute),
            });
        case 'weekly':
            return t(
                'automation.schedule.weekly_at',
                'Every {{day}} at {{time}}',
                {
                    day: weekdayLabels(t)[preset.weekday],
                    time: formatTime(preset.hour, preset.minute),
                }
            );
        case 'custom':
            return t('automation.schedule.custom_expr', 'Cron: {{expr}}', {
                expr: preset.expression,
            });
    }
}
