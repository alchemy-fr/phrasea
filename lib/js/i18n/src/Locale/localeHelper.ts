let currentLanguages: string[] = [...window.navigator.languages];

export function setCurrentLocale(locale: string | undefined) {
    currentLanguages = [
        ...(locale ? [locale] : []),
        ...window.navigator.languages.filter(l => l !== locale),
    ];
}


export function getBestLocaleOfTranslations(
    fieldTranslations: Record<string, any> | undefined,
    languages?: readonly string[]
): string | undefined {
    if (!fieldTranslations) {
        return;
    }

    const langList = languages ?? currentLanguages;

    for (const _lang of langList) {
        let language = normalizeLocale(_lang);
        if (Object.prototype.hasOwnProperty.call(fieldTranslations, language)) {
            return language;
        }

        if (language.indexOf('_') > 0) {
            const [l] = language.split('_');
            if (Object.prototype.hasOwnProperty.call(fieldTranslations, l)) {
                return l;
            }

            language = l;
        }

        for (const lo in fieldTranslations) {
            const [_l] = normalizeLocale(lo).split('_');
            if (_l === language) {
                return lo;
            }
        }
    }
}

export function getBestLocale(
    locales: string[],
    languages?: readonly string[]
): string | undefined {
    if (locales.length === 0) {
        return undefined;
    }

    const langList = languages ?? currentLanguages;

    for (const _lang of langList) {
        let language = normalizeLocale(_lang);
        if (locales.includes(language)) {
            return language;
        }

        if (language.indexOf('_') > 0) {
            const [l] = language.split('_');
            if (locales.includes(l)) {
                return l;
            }

            language = l;
        }

        for (const lo of locales) {
            const [_l] = normalizeLocale(lo).split('_');
            if (_l === language) {
                return lo;
            }
        }
    }
}

function normalizeLocale(l: string): string {
    return l.replace('-', '_');
}

export function getBestFieldTranslatedValue<T>(
    translations:
        | Readonly<Record<string, Readonly<Record<string, T>>>>
        | undefined,
    field: string,
    fallback: T,
    fallbackLocale?: string | undefined,
    languages?: readonly string[]
): T {
    if (!translations || !translations[field]) {
        return fallback;
    }

    return getBestTranslatedValue(translations[field], fallback, fallbackLocale, languages);
}


export function getBestTranslatedValue<T>(
    translations:
        | Readonly<Record<string, T>>
        | undefined,
    fallback: T,
    fallbackLocale?: string | undefined,
    languages?: readonly string[]
): T {
    if (!translations) {
        return fallback;
    }

    const tr = {
        ...(translations ?? {}),
    };

    if (
        fallbackLocale &&
        !Object.prototype.hasOwnProperty.call(tr, fallbackLocale)
    ) {
        tr[fallbackLocale] = fallback;
    }

    const l = getBestLocaleOfTranslations(tr, languages);

    return l ? tr[l] : fallback;
}
