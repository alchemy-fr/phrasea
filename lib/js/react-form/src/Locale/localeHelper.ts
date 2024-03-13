import {Translation} from "../Translations/TranslationsWidget";
import {FieldTranslations, Translations} from "../types";


export function getBestLocaleOfTranslations(
    fieldTranslations: Record<string, any> | undefined,
    languages?: readonly string[],
): string | undefined {
    if (!fieldTranslations) {
        return;
    }

    const langList = languages ?? window.navigator.languages;

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
    languages?: readonly string[],
): string | undefined {
    if (locales.length === 0) {
        return undefined;
    }

    const langList = languages ?? window.navigator.languages;

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

export function getBestTranslatedValue<T>(
    translations: Readonly<Record<string, Readonly<Record<string, T>>>>,
    field: string,
    fallback: T,
    fallbackLocale?: string | undefined,
    languages?: readonly string[],
): T {
    if (!translations[field]) {
        return fallback;
    }

    const tr = {
        ...(translations[field] ?? {}),
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

export function getFieldTranslationCount(
    translations: Translations | undefined,
    field: string,
): number {
    if (!translations) {
        return 0;
    }

    return Object.prototype.hasOwnProperty.call(translations, field)
        ? Object.keys(translations[field]).length
        : 0;
}

export function getFieldTranslationsList(
    translations: Translations | undefined,
    field: string,
): Translation[] {
    if (getFieldTranslationCount(translations, field) === 0) {
        return [];
    }

    return Object.keys(translations![field]).map(locale => ({
        locale,
        value: translations![field][locale],
    }));
}

export function getFieldTranslationsObject(
    translations: Translation[],
): FieldTranslations | undefined {
    const tr: FieldTranslations = {};

    translations.forEach(t => {
        tr[t.locale] = t.value;
    });

    return tr;
}
