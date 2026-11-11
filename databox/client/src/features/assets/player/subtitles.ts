import {AttributeType, type Attribute} from '@/types/api';
import {getPreferredLanguages, NO_LOCALE, pickLocale} from '@/lib/utils/locale';

/** A subtitle track of a video, from a WebVTT attribute value */
export type SubtitleTrack = {
    id: string;
    label: string;
    /** BCP 47 language of the track, none for a value without a locale */
    locale?: string;
    /** The WebVTT content */
    src: string;
    /** Shown when the video is loaded */
    default?: boolean;
};

/** `fr_FR` → `fr-FR`; none for a value without a locale */
function toLanguageTag(locale: string | undefined): string | undefined {
    return locale && locale !== NO_LOCALE
        ? locale.replace(/_/g, '-')
        : undefined;
}

function languageName(tag: string, uiLanguage: string): string {
    try {
        const name = new Intl.DisplayNames([uiLanguage], {
            type: 'language',
        }).of(tag);
        if (name && name !== tag) {
            return name.charAt(0).toLocaleUpperCase(uiLanguage) + name.slice(1);
        }
    } catch {
        // invalid tag
    }

    return tag;
}

/**
 * The value of the first WebVTT attribute shown by the attributes panel: the
 * one of the preferred language, else of the first locale, else the one
 * without a locale (see `groupAttributes()`)
 */
function defaultValue(
    values: Attribute[],
    languages: readonly string[]
): Attribute | undefined {
    const first = values[0];
    if (!first) {
        return undefined;
    }
    const ofDefinition = values.filter(
        a => a.definition.id === first.definition.id
    );
    const locales = ofDefinition
        .map(a => a.locale ?? NO_LOCALE)
        .filter(l => l !== NO_LOCALE);
    const best = pickLocale(locales, languages) ?? locales[0];

    return (
        ofDefinition.find(a => best !== undefined && a.locale === best) ??
        ofDefinition.find(a => (a.locale ?? NO_LOCALE) === NO_LOCALE) ??
        first
    );
}

/**
 * The subtitles of an asset: one track per value of its WebVTT attributes,
 * in the language of the value. A track is named after its attribute, with
 * its language when there are several. One track is shown by default: the
 * value the attributes panel shows of the first WebVTT attribute, so that
 * the subtitles match the transcript.
 */
export function getSubtitleTracks(
    attributes: Attribute[] | undefined,
    uiLanguage: string = getPreferredLanguages()[0] ?? 'en',
    languages: readonly string[] = getPreferredLanguages()
): SubtitleTrack[] {
    const values = (attributes ?? []).filter(
        a =>
            a.definition?.type === AttributeType.WebVtt &&
            typeof a.value === 'string' &&
            a.value.trim() !== ''
    );
    const single = values.length === 1;
    const shown = defaultValue(values, languages);

    return values.map(attribute => {
        const {definition} = attribute;
        const name = definition.displayName ?? definition.name;
        const locale = toLanguageTag(attribute.locale);

        return {
            id: attribute.id,
            label:
                single || !locale
                    ? name
                    : `${name} (${languageName(locale, uiLanguage)})`,
            locale,
            src: attribute.value as string,
            ...(attribute === shown ? {default: true} : {}),
        };
    });
}
