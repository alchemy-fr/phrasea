import {
    mergeCspSources,
    originOf,
    stackCspSources,
    type CspSources,
} from '@alchemy/core/security/csp';

/**
 * Enforced before the policy restricted the scripts: they stay so while the
 * whole policy is only reported (`CSP_REPORT_ONLY`).
 */
export const ENFORCED_DIRECTIVES = ['frame-src', 'frame-ancestors'];

/**
 * The sources of the Content Security Policy of the pages: the services of
 * the stack, the APIs, the frames they display (the Keycloak silent sign-in)
 * and the sites that may embed them — the clients of the stack and the admins
 * of the APIs, whose services menu frames the dashboard, plus
 * `ALLOWED_FRAME_ANCESTORS`, a space separated list of sources.
 */
export function contentSecuritySources(
    env: Record<string, string | undefined>
): CspSources {
    const apis = [
        originOf(env.DATABOX_API_URL),
        originOf(env.EXPOSE_API_URL),
        originOf(env.UPLOADER_API_URL),
    ];

    return mergeCspSources(stackCspSources(env), {
        connectSrc: apis,
        frameAncestors: [
            originOf(env.DATABOX_CLIENT_URL),
            originOf(env.EXPOSE_CLIENT_URL),
            originOf(env.UPLOADER_CLIENT_URL),
            ...apis,
            env.ALLOWED_FRAME_ANCESTORS,
        ],
    });
}
