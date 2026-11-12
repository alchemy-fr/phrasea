import type {Publication, PublicationRef} from '@/types/api';

/** First segments that are not publication slugs */
export const RESERVED_SEGMENTS = [
    'auth',
    'embed',
    'profiles',
    'publications',
    'api',
    's',
];

/** The segment addressing a publication in its URLs: its slug, else its id */
export function publicationKey(
    publication: Pick<Publication | PublicationRef, 'id' | 'slug'>
): string {
    return encodeURIComponent(publication.slug || publication.id);
}

export const routes = {
    home: () => '/',
    publication: (publication: Pick<PublicationRef, 'id' | 'slug'>) =>
        `/${publicationKey(publication)}`,
    publicationAsset: (
        publication: Pick<PublicationRef, 'id' | 'slug'>,
        assetId: string
    ) => `/${publicationKey(publication)}/${encodeURIComponent(assetId)}`,
    embed: (assetId: string) => `/embed/${encodeURIComponent(assetId)}`,
    /** The permalink of a share link */
    share: (token: string) => `/s/${encodeURIComponent(token)}`,
    publications: () => '/publications',
    publicationEdit: (id: string) =>
        `/publications/${encodeURIComponent(id)}/edit`,
    profiles: () => '/profiles',
    profileCreate: () => '/profiles/create',
    profileEdit: (id: string) => `/profiles/${encodeURIComponent(id)}/edit`,
};
