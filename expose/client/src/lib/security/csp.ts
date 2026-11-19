import {
    mergeCspSources,
    originOf,
    stackCspSources,
    type CspSources,
} from '@alchemy/next-core/security/csp';

/**
 * Enforced before the policy restricted the scripts: they stay so while the
 * whole policy is only reported (`CSP_REPORT_ONLY`).
 */
export const ENFORCED_DIRECTIVES = ['frame-src', 'frame-ancestors'];

/**
 * The sources of the Content Security Policy of the pages: the services of
 * the stack, the API, the frames they display (the Keycloak silent sign-in,
 * the dashboard) and the sites that may embed them, `ALLOWED_FRAME_ANCESTORS`
 * being a space separated list of sources.
 */
export function contentSecuritySources(
    env: Record<string, string | undefined>
): CspSources {
    const api = originOf(env.EXPOSE_API_URL);

    return mergeCspSources(stackCspSources(env), {
        connectSrc: [api],
        imgSrc: [api],
        mediaSrc: [api],
        frameSrc: [originOf(env.DASHBOARD_CLIENT_URL)],
        frameAncestors: [env.ALLOWED_FRAME_ANCESTORS],
    });
}
