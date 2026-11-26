/**
 * A boolean environment variable: on with `1`, `true`, `on`, `y` or `yes`
 * (any case), `fallback` when unset or empty.
 */
export function envBool(value: string | undefined, fallback = false): boolean {
    if (value === undefined || value === '') {
        return fallback;
    }

    return ['1', 'true', 'on', 'y', 'yes'].includes(value.toLowerCase());
}
