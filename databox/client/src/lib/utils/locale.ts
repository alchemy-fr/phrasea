/**
 * Locale negotiation helpers shared by translatable fields (tags, collection
 * names, attribute values...). `preferred` is the ordered list of languages to
 * try (UI language first, then browser languages).
 */

let preferredLanguages: string[] = ['en'];

export function setPreferredLanguages(languages: readonly string[]): void {
    preferredLanguages = [...languages];
}

export function getPreferredLanguages(): readonly string[] {
    return preferredLanguages;
}

function normalize(l: string): string {
    return l.replace('-', '_');
}

export function pickLocale(
    available: readonly string[],
    languages: readonly string[] = preferredLanguages
): string | undefined {
    if (available.length === 0) {
        return undefined;
    }

    for (const raw of languages) {
        const lang = normalize(raw);
        if (available.includes(lang)) {
            return lang;
        }
        const [short] = lang.split('_');
        if (available.includes(short)) {
            return short;
        }
        const match = available.find(a => normalize(a).split('_')[0] === short);
        if (match) {
            return match;
        }
    }

    return undefined;
}

export function pickTranslation<T>(
    translations: Readonly<Record<string, T>> | undefined,
    fallback: T,
    languages?: readonly string[]
): T {
    if (!translations) {
        return fallback;
    }
    const locale = pickLocale(Object.keys(translations), languages);

    return locale ? translations[locale] : fallback;
}

export function pickFieldTranslation<T>(
    translations:
        | Readonly<Record<string, Readonly<Record<string, T>>>>
        | undefined,
    field: string,
    fallback: T
): T {
    return pickTranslation(translations?.[field], fallback);
}

const rtlLanguages = ['ar', 'he', 'fa', 'ur'];

export function isRtl(locale: string | undefined): boolean {
    return !!locale && rtlLanguages.includes(locale.split(/[-_]/)[0]);
}

export const NO_LOCALE = '_';
