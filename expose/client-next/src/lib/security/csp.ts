/**
 * The Content Security Policy of the pages: the frames they may display
 * (the Keycloak silent sign-in, the dashboard) and the sites that may embed
 * them, `ALLOWED_FRAME_ANCESTORS` being a space separated list of sources.
 */
export function buildContentSecurityPolicy(
    env: Record<string, string | undefined>
): string {
    const sources = (...values: (string | undefined)[]) =>
        values
            .flatMap(v => (v ?? '').split(/\s+/))
            .filter(Boolean)
            .join(' ');
    // A source with a path only matches that path: Keycloak may be served
    // under one (`/auth`), its pages are below
    const origin = (url: string | undefined) => {
        try {
            return url ? new URL(url).origin : undefined;
        } catch {
            return undefined;
        }
    };

    return [
        `frame-src ${sources("'self'", origin(env.KEYCLOAK_URL), origin(env.DASHBOARD_CLIENT_URL))}`,
        `frame-ancestors ${sources("'self'", env.ALLOWED_FRAME_ANCESTORS)}`,
    ].join('; ');
}
