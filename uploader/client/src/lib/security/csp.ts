import {
    mergeCspSources,
    originOf,
    stackCspSources,
    type CspSources,
} from '@alchemy/core/security/csp';

/**
 * The sources of the Content Security Policy of the pages: the services of
 * the stack (the files are uploaded to the bucket), the API, and the
 * dashboard framed as the services menu.
 */
export function contentSecuritySources(
    env: Record<string, string | undefined>
): CspSources {
    const api = originOf(env.UPLOADER_API_URL);

    return mergeCspSources(stackCspSources(env), {
        connectSrc: [api],
        imgSrc: [api],
        frameSrc: [originOf(env.DASHBOARD_CLIENT_URL)],
    });
}
