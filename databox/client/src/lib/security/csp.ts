import {
    mergeCspSources,
    originOf,
    stackCspSources,
    type CspSources,
} from '@alchemy/core/security/csp';

/** Where Mapbox GL loads its styles, tiles and fonts, and sends its events */
const MAPBOX_SOURCES = [
    'https://api.mapbox.com',
    'https://*.tiles.mapbox.com',
    'https://events.mapbox.com',
];

/**
 * The sources of the Content Security Policy of the pages: the services of
 * the stack, the API (and the files it serves), the realtime server
 * (`SOCKUDO_HOST`), Mapbox when a token is set, and the dashboard framed as
 * the services menu. Any site may embed the pages (the share pages are made
 * to be).
 */
export function contentSecuritySources(
    env: Record<string, string | undefined>
): CspSources {
    const api = originOf(env.DATABOX_API_URL);
    const mapbox =
        env.MAPBOX_TOKEN && env.MAPBOX_TOKEN !== 'define-me'
            ? MAPBOX_SOURCES
            : [];
    const realtime = env.SOCKUDO_HOST?.trim()
        ? `wss://${env.SOCKUDO_HOST.trim()}`
        : undefined;

    return mergeCspSources(stackCspSources(env), {
        connectSrc: [api, realtime, ...mapbox],
        imgSrc: [api],
        mediaSrc: [api],
        frameSrc: [originOf(env.DASHBOARD_CLIENT_URL)],
    });
}
