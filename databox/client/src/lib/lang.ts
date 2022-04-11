export function isRtlLocale(locale: string | undefined): boolean {
    return [
        'ar',
        'fa',
        'he',
        'iw',
        'ur',
        'yi',
        'ji',
    ].includes((locale || '').toLowerCase());
}
