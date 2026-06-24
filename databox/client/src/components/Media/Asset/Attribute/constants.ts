export const NO_LOCALE = '_';

export function isNoLocale(locale: string | null | undefined): boolean {
    return (locale ?? NO_LOCALE) === NO_LOCALE;
}
