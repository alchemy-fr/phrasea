import {AttributeType, type Attribute} from '@/types/api';
import {getPreferredLanguages, NO_LOCALE} from '@/lib/utils/locale';

/** A subtitle track of a video, from a WebVTT attribute value */
export type SubtitleTrack = {
    id: string;
    label: string;
    /** BCP 47 language of the track, none for a value without a locale */
    locale?: string;
    /** The WebVTT content */
    src: string;
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
 * The subtitles of an asset: one track per value of its WebVTT attributes,
 * in the language of the value. A track is named after its attribute, with
 * its language when there are several.
 */
export function getSubtitleTracks(
    attributes: Attribute[] | undefined,
    uiLanguage: string = getPreferredLanguages()[0] ?? 'en'
): SubtitleTrack[] {
    const values = (attributes ?? []).filter(
        a =>
            a.definition?.type === AttributeType.WebVtt &&
            typeof a.value === 'string' &&
            a.value.trim() !== ''
    );
    const single = values.length === 1;

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
        };
    });
}
