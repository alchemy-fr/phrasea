const localeToCountry: Record<string, string> = {
    en: 'gb',
    fr: 'fr',
    de: 'de',
    es: 'es',
    it: 'it',
    pt: 'pt',
    nl: 'nl',
    zh: 'cn',
    ja: 'jp',
    ko: 'kr',
    ar: 'sa',
    ru: 'ru',
    pl: 'pl',
    sv: 'se',
    da: 'dk',
    fi: 'fi',
    no: 'no',
    cs: 'cz',
    el: 'gr',
    he: 'il',
    tr: 'tr',
    uk: 'ua',
    hi: 'in',
};

function toEmoji(country: string): string {
    return country
        .toUpperCase()
        .split('')
        .map(c => String.fromCodePoint(0x1f1e6 + c.charCodeAt(0) - 65))
        .join('');
}

/**
 * Emoji flag for a locale (`fr`, `en_GB`, `pt-BR`...).
 */
export function Flag({
    locale,
    className,
}: {
    locale: string;
    className?: string;
}) {
    const [lang, region] = locale.split(/[-_]/);
    const country = (
        region ??
        localeToCountry[lang.toLowerCase()] ??
        lang
    ).toLowerCase();
    if (country.length !== 2) {
        return null;
    }

    return (
        <span className={className} aria-hidden>
            {toEmoji(country)}
        </span>
    );
}
