import 'server-only';
import {cache} from 'react';
import type {Metadata} from 'next';
import {
    httpGet,
    verifySslFromEnv,
    type HttpGet,
} from '@alchemy/core/server/http';
import {NO_INDEX, pageMetadata} from '@alchemy/core/seo/metadata';
import {getServerConfig} from '@/lib/config/server';
import {APP_NAME} from './app';
import type {Asset, Share} from '@/types/api';

/** The link preview must not hold the page back */
export const PREVIEW_TIMEOUT_MS = 3000;

export type ApiAccess = {
    apiUrl: string;
    verifySsl: boolean;
    get?: HttpGet;
};

/** What an anonymous visitor reads of a share */
export type PublicShare = Pick<Share, 'id' | 'assets'>;

/**
 * The share as its public page reads it, null when it cannot be shown
 * (disabled, expired, deleted, API unreachable…).
 */
export async function loadPublicShare(
    id: string,
    token: string,
    {apiUrl, verifySsl, get = httpGet}: ApiAccess
): Promise<PublicShare | null> {
    try {
        const {status, body} = await get(
            `${apiUrl.replace(/\/+$/, '')}/shares/${encodeURIComponent(id)}/public?token=${encodeURIComponent(token)}`,
            {
                // Anonymous: the preview shows only what is public
                headers: {Accept: 'application/json'},
                rejectUnauthorized: verifySsl,
                timeout: PREVIEW_TIMEOUT_MS,
            }
        );
        if (status !== 200) {
            return null;
        }
        const share = JSON.parse(body) as PublicShare | null;

        return share && Array.isArray(share.assets) && share.assets.length > 0
            ? share
            : null;
    } catch {
        return null;
    }
}

/** Shared by the page and the image route of a same request */
export const fetchPublicShare = cache((id: string, token: string) =>
    loadPublicShare(id, token, {
        apiUrl: getServerConfig().apiUrl,
        verifySsl: verifySslFromEnv(process.env),
    })
);

/** The title of the preview: the asset, or the first one and how many more */
export function shareTitle(share: PublicShare): string | undefined {
    const [first, ...others] = share.assets;
    if (!first?.name) {
        return undefined;
    }

    return others.length > 0 ? `${first.name} (+${others.length})` : first.name;
}

function imageFile(asset: Asset): string | undefined {
    for (const rendition of [asset.thumbnail, asset.preview]) {
        const file = rendition?.file;
        if (
            file?.url &&
            file.type?.startsWith('image/') &&
            !file.type.includes('svg')
        ) {
            return file.url;
        }
    }

    return undefined;
}

/**
 * The URL of the first image of the share fit for a link preview, signed for
 * a while only: served by the client under a stable URL (app/api/og).
 */
export function sharePreviewImageUrl(share: PublicShare): string | undefined {
    for (const asset of share.assets) {
        const url = imageFile(asset);
        if (url) {
            return url;
        }
    }

    return undefined;
}

export function shareOgImageRoute(id: string, token: string): string {
    return `/api/og/share/${encodeURIComponent(id)}/${encodeURIComponent(token)}`;
}

/** The metadata of the public page of a share: never indexed, its URL holds the token */
export function sharePageMetadata(
    id: string,
    token: string,
    share: PublicShare | null
): Metadata {
    const title = share ? shareTitle(share) : undefined;
    if (!share || !title) {
        return {robots: NO_INDEX};
    }

    return {
        ...pageMetadata({
            title,
            image: sharePreviewImageUrl(share)
                ? shareOgImageRoute(id, token)
                : undefined,
            url: `/s/${encodeURIComponent(id)}/${encodeURIComponent(token)}`,
            siteName: APP_NAME,
        }),
        robots: NO_INDEX,
    };
}
