import 'server-only';
import {cache} from 'react';
import type {Metadata} from 'next';
import {
    httpGet,
    verifySslFromEnv,
    type HttpGet,
} from '@alchemy/next-core/server/http';
import {pageMetadata, plainText} from '@alchemy/next-core/seo/metadata';
import {getServerConfig} from '@/lib/config/server';
import {findAsset} from '@/features/publication/assets';
import {publicationKey, routes} from '@/lib/routes';
import {APP_NAME} from './app';
import type {Asset, Publication, PublicShareResolution} from '@/types/api';

/** The link preview must not hold the page back */
export const PREVIEW_TIMEOUT_MS = 3000;

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

/** Shared by the layout and the page of a same request */
export const fetchPublicPublication = cache(
    (key: string, shareToken?: string) =>
        loadPublicPublication(key, shareToken, apiAccess())
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

export const ogImageRoutes = {
    publication: (publication: Publication, shareToken?: string) =>
        withShare(
            `/api/og/publication/${publicationKey(publication)}`,
            shareToken
        ),
    asset: (publication: Publication, asset: Asset) =>
        `/api/og/publication/${publicationKey(publication)}/asset/${encodeURIComponent(asset.id)}`,
};

function description(html: string | null | undefined): string | undefined {
    return (html && plainText(html)) || undefined;
}

/** The metadata of the page of a publication */
export function publicationPageMetadata(
    publication: Publication,
    {url, shareToken}: {url?: string; shareToken?: string} = {}
): Metadata {
    return pageMetadata({
        title: publication.title!,
        description: description(publication.description),
        image: previewImageUrl(publication.cover)
            ? ogImageRoutes.publication(publication, shareToken)
            : undefined,
        url: url ?? routes.publication(publication),
        siteName: APP_NAME,
    });
}

/** The metadata of the page of an asset, its publication's when unknown */
export function assetPageMetadata(
    publication: Publication,
    assetKey: string
): Metadata {
    let asset: Asset | undefined;
    try {
        asset = findAsset(publication.assets ?? [], assetKey);
    } catch {
        // A malformed URL segment
    }
    if (!asset) {
        return publicationPageMetadata(publication);
    }
    const assetTitle = asset.title || asset.originalName;

    return pageMetadata({
        title: assetTitle
            ? `${assetTitle} · ${publication.title}`
            : publication.title!,
        description:
            description(asset.description) ??
            description(publication.description),
        image: previewImageUrl(asset)
            ? ogImageRoutes.asset(publication, asset)
            : previewImageUrl(publication.cover)
              ? ogImageRoutes.publication(publication)
              : undefined,
        url: routes.publicationAsset(publication, asset.id),
        siteName: APP_NAME,
    });
}

/** A URL segment, undefined when it is not valid percent-encoding */
export function decodeSegment(segment: string): string | undefined {
    try {
        return decodeURIComponent(segment);
    } catch {
        return undefined;
    }
}
