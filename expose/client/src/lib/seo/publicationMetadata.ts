import 'server-only';
import {cache} from 'react';
import {unstable_cache} from 'next/cache';
import type {Metadata} from 'next';
import {
    httpGet,
    verifySslFromEnv,
    type HttpGet,
} from '@alchemy/core/server/http';
import {NO_INDEX, pageMetadata, plainText} from '@alchemy/core/seo/metadata';
import {getServerConfig} from '@/lib/config/server';
import {findAsset} from '@/features/publication/assets';
import {publicationKey, routes} from '@/lib/routes';
import {APP_NAME} from './app';
import type {Asset, Publication, PublicShareResolution} from '@/types/api';

/** The link preview must not hold the page back */
export const PREVIEW_TIMEOUT_MS = 3000;

/**
 * How long the metadata of a publication is kept between requests: moving
 * from one asset to another in the lightbox renders the metadata again.
 */
export const METADATA_REVALIDATE_S = 60;

export type ApiAccess = {
    apiUrl: string;
    verifySsl: boolean;
    get?: HttpGet;
};

function apiAccess(): ApiAccess {
    return {
        apiUrl: getServerConfig().apiUrl,
        verifySsl: verifySslFromEnv(process.env),
    };
}

/** A share token as the client stores it (see lib/api/shareTokens) */
export function isShareToken(value: unknown): value is string {
    return typeof value === 'string' && /^[A-Za-z0-9]{1,64}$/.test(value);
}

async function getJson<T>(
    path: string,
    {apiUrl, verifySsl, get = httpGet}: ApiAccess,
    headers: Record<string, string> = {}
): Promise<T | null> {
    try {
        const {status, body} = await get(
            `${apiUrl.replace(/\/+$/, '')}${path}`,
            {
                // Anonymous: the preview shows only what is public
                headers: {Accept: 'application/json', ...headers},
                rejectUnauthorized: verifySsl,
                timeout: PREVIEW_TIMEOUT_MS,
            }
        );
        if (status !== 200) {
            return null;
        }
        const data: unknown = JSON.parse(body);

        return data && typeof data === 'object' ? (data as T) : null;
    } catch {
        return null;
    }
}

/**
 * The publication as an anonymous visitor reads it (with the token of a
 * share link when given), null when it has nothing to show: disabled,
 * protected without being publicly listed, unknown, API unreachable…
 */
export async function loadPublicPublication(
    key: string,
    shareToken: string | undefined,
    access: ApiAccess
): Promise<Publication | null> {
    const publication = await getJson<Publication>(
        `/publications/${encodeURIComponent(key)}`,
        access,
        isShareToken(shareToken) ? {'X-Share-Tokens': shareToken} : {}
    );

    return publication?.title ? publication : null;
}

/** Shared by the routes of a same request */
export const fetchPublicPublication = cache(
    (key: string, shareToken?: string) =>
        loadPublicPublication(key, shareToken, apiAccess())
);

/** What the link preview reads of an asset: none of its signed URLs */
export type AssetMetadata = Pick<
    Asset,
    'id' | 'slug' | 'title' | 'originalName' | 'description'
> & {hasPreviewImage: boolean};

/**
 * What the link preview reads of a publication. Kept between requests
 * (see fetchPublicationMetadata): holds no signed URL, which would outlive
 * its signature in the cache.
 */
export type PublicationMetadata = Pick<
    Publication,
    'id' | 'slug' | 'title' | 'description' | 'publiclyListed'
> & {
    cover: AssetMetadata | null;
    assets: AssetMetadata[];
};

function toAssetMetadata(asset: Asset): AssetMetadata {
    return {
        id: asset.id,
        slug: asset.slug,
        title: asset.title,
        originalName: asset.originalName,
        description: asset.description,
        hasPreviewImage: !!previewImageUrl(asset),
    };
}

export function toPublicationMetadata(
    publication: Publication
): PublicationMetadata {
    return {
        id: publication.id,
        slug: publication.slug,
        title: publication.title,
        description: publication.description,
        publiclyListed: publication.publiclyListed,
        cover: publication.cover ? toAssetMetadata(publication.cover) : null,
        assets: (publication.assets ?? []).map(toAssetMetadata),
    };
}

/** The metadata of a publication as an anonymous visitor reads it, cached */
export function publicationMetadataLoader(
    access: () => ApiAccess
): (key: string) => Promise<PublicationMetadata | null> {
    return unstable_cache(
        async (key: string) => {
            const publication = await loadPublicPublication(
                key,
                undefined,
                access()
            );

            return publication ? toPublicationMetadata(publication) : null;
        },
        ['expose-publication-metadata'],
        {revalidate: METADATA_REVALIDATE_S}
    );
}

/** Shared by the layout and the asset pages of a publication */
export const fetchPublicationMetadata = cache(
    publicationMetadataLoader(apiAccess)
);

export async function loadShareResolution(
    token: string,
    access: ApiAccess
): Promise<PublicShareResolution | null> {
    if (!isShareToken(token)) {
        return null;
    }
    const resolution = await getJson<PublicShareResolution>(
        `/publication-shares/resolve/${encodeURIComponent(token)}`,
        access
    );

    return resolution?.publication?.id ? resolution : null;
}

export const fetchShareResolution = cache((token: string) =>
    loadShareResolution(token, apiAccess())
);

function isPreviewImage(type: string | null | undefined): boolean {
    return !!type && type.startsWith('image/') && !type.includes('svg');
}

/**
 * The URL of the image of an asset fit for a link preview: its thumbnail,
 * else its preview. Signed for a while only: served by the client under a
 * stable URL (app/api/og).
 */
export function previewImageUrl(
    asset: Asset | null | undefined
): string | undefined {
    if (!asset) {
        return undefined;
    }
    if (asset.thumbUrl && isPreviewImage(asset.thumbType)) {
        return asset.thumbUrl;
    }
    if (asset.previewUrl && isPreviewImage(asset.previewType)) {
        return asset.previewUrl;
    }

    return undefined;
}

function withShare(path: string, shareToken: string | undefined): string {
    return isShareToken(shareToken)
        ? `${path}?share=${encodeURIComponent(shareToken)}`
        : path;
}

type PublicationKey = Pick<Publication, 'id' | 'slug'>;

export const ogImageRoutes = {
    publication: (publication: PublicationKey, shareToken?: string) =>
        withShare(
            `/api/og/publication/${publicationKey(publication)}`,
            shareToken
        ),
    asset: (publication: PublicationKey, asset: Pick<Asset, 'id'>) =>
        `/api/og/publication/${publicationKey(publication)}/asset/${encodeURIComponent(asset.id)}`,
};

function description(html: string | null | undefined): string | undefined {
    return (html && plainText(html)) || undefined;
}

/** Unlisted publications stay out of the search engines */
function robots(publication: PublicationMetadata): Metadata {
    return publication.publiclyListed ? {} : {robots: NO_INDEX};
}

/** The metadata of the page of a publication */
export function publicationPageMetadata(
    publication: PublicationMetadata,
    {url, shareToken}: {url?: string; shareToken?: string} = {}
): Metadata {
    return {
        ...pageMetadata({
            title: publication.title!,
            description: description(publication.description),
            image: publication.cover?.hasPreviewImage
                ? ogImageRoutes.publication(publication, shareToken)
                : undefined,
            url: url ?? routes.publication(publication),
            siteName: APP_NAME,
        }),
        ...robots(publication),
    };
}

/** The metadata of the page of an asset, its publication's when unknown */
export function assetPageMetadata(
    publication: PublicationMetadata,
    assetKey: string
): Metadata {
    let asset: AssetMetadata | undefined;
    try {
        asset = findAsset(publication.assets, assetKey);
    } catch {
        // A malformed URL segment
    }
    if (!asset) {
        return publicationPageMetadata(publication);
    }
    const assetTitle = asset.title || asset.originalName;

    return {
        ...pageMetadata({
            title: assetTitle
                ? `${assetTitle} · ${publication.title}`
                : publication.title!,
            description:
                description(asset.description) ??
                description(publication.description),
            image: asset.hasPreviewImage
                ? ogImageRoutes.asset(publication, asset)
                : publication.cover?.hasPreviewImage
                  ? ogImageRoutes.publication(publication)
                  : undefined,
            url: routes.publicationAsset(publication, asset.id),
            siteName: APP_NAME,
        }),
        ...robots(publication),
    };
}

/** A URL segment, undefined when it is not valid percent-encoding */
export function decodeSegment(segment: string): string | undefined {
    try {
        return decodeURIComponent(segment);
    } catch {
        return undefined;
    }
}
