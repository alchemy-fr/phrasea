import 'server-only';
import type {TicketSessionContext} from '@/features/ticketing/types';
import {getKeycloakUserInfoUrl} from './config';

type AccessTokenClaims = {
    sub?: string;
    sid?: string;
    exp?: number;
    azp?: string;
    iss?: string;
    preferred_username?: string;
    email?: string;
    name?: string;
    groups?: string[];
    roles?: string[];
    realm_access?: {roles?: string[]};
};

export class InvalidTokenError extends Error {
    constructor(message = 'Invalid access token') {
        super(message);
        this.name = 'InvalidTokenError';
    }
}

/** Decodes the payload of a JWT without verifying it */
function decodeClaims(token: string): AccessTokenClaims {
    const payload = token.split('.')[1];
    if (!payload) {
        return {};
    }
    try {
        return JSON.parse(
            Buffer.from(payload, 'base64url').toString('utf8')
        ) as AccessTokenClaims;
    } catch {
        return {};
    }
}

export function getBearerToken(request: Request): string | undefined {
    const header = request.headers.get('authorization') ?? '';
    const match = /^Bearer\s+(.+)$/i.exec(header.trim());

    return match?.[1];
}

/**
 * Rebuilds the reporter's session from their access token. The token is
 * validated against Keycloak (userinfo endpoint) so a ticket can never be
 * opened on behalf of someone else; claims are then read from the - now
 * trusted - token for the roles and groups that userinfo does not return.
 */
export async function resolveSession(
    token: string
): Promise<TicketSessionContext> {
    const response = await fetch(getKeycloakUserInfoUrl(), {
        headers: {Authorization: `Bearer ${token}`},
        cache: 'no-store',
    });

    if (response.status === 401 || response.status === 403) {
        throw new InvalidTokenError();
    }
    if (!response.ok) {
        throw new Error(
            `Keycloak userinfo failed with status ${response.status}`
        );
    }

    const userInfo = (await response.json()) as AccessTokenClaims;
    const claims = decodeClaims(token);
    const userId = userInfo.sub ?? claims.sub;
    if (!userId) {
        throw new InvalidTokenError();
    }

    return {
        userId,
        username: userInfo.preferred_username ?? claims.preferred_username,
        email: userInfo.email ?? claims.email,
        name: userInfo.name ?? claims.name,
        roles: [
            ...new Set([
                ...(claims.roles ?? []),
                ...(claims.realm_access?.roles ?? []),
            ]),
        ],
        groups: claims.groups ?? [],
        sessionId: claims.sid,
        clientId: claims.azp ?? process.env.CLIENT_ID,
        realm: process.env.KEYCLOAK_REALM_NAME || 'phrasea',
        expiresAt: claims.exp
            ? new Date(claims.exp * 1000).toISOString()
            : undefined,
    };
}
