import type {WebVTTLink} from '@/types/api';

/** A subtitle track of a video */
export type SubtitleTrack = {
    id: string;
    label: string;
    /** BCP 47 language of the track */
    locale?: string;
    /** The WebVTT content */
    src: string;
};

/** `fr_FR` → `fr-FR` */
export function toLanguageTag(
    locale: string | null | undefined
): string | undefined {
    return locale ? locale.replace(/_/g, '-') : undefined;
}

export function languageName(tag: string, uiLanguage: string): string {
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
 * The subtitle tracks of an asset, from its WebVTT links. The files are
 * fetched, then given to the player as object URLs: a `<track>` of another
 * origin than the page would need the video itself to be loaded with CORS.
 * A track that cannot be loaded is left out.
 */
export async function loadSubtitleTracks(
    links: WebVTTLink[],
    uiLanguage: string,
    signal?: AbortSignal
): Promise<SubtitleTrack[]> {
    const tracks = await Promise.all(
        links
            .filter(
                l => !l.kind || l.kind === 'subtitles' || l.kind === 'captions'
            )
            .map(async (link): Promise<SubtitleTrack | undefined> => {
                try {
                    const res = await fetch(link.url, {signal});
                    if (!res.ok) {
                        return undefined;
                    }
                    const locale = toLanguageTag(link.locale);

                    return {
                        id: link.id,
                        label:
                            link.label ||
                            (locale
                                ? languageName(locale, uiLanguage)
                                : link.id),
                        locale,
                        src: await res.text(),
                    };
                } catch {
                    return undefined;
                }
            })
    );

    return tracks.filter((t): t is SubtitleTrack => !!t);
}
