/**
 * The Content Security Policy of the pages: the frames they may display (the
 * Keycloak silent sign-in) and the sites that may embed them — the clients of
 * the stack, whose services menu frames the dashboard, plus
 * `ALLOWED_FRAME_ANCESTORS`, a space separated list of sources.
 */
export function buildContentSecurityPolicy(
    env: Record<string, string | undefined>
): string {
    const sources = (...values: (string | undefined)[]) =>
        [
            ...new Set(
                values.flatMap(v => (v ?? '').split(/\s+/)).filter(Boolean)
            ),
        ].join(' ');
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
        `frame-src ${sources("'self'", origin(env.KEYCLOAK_URL))}`,
        `frame-ancestors ${sources(
            "'self'",
            origin(env.DATABOX_CLIENT_URL),
            origin(env.EXPOSE_CLIENT_URL),
            origin(env.UPLOADER_CLIENT_URL),
            env.ALLOWED_FRAME_ANCESTORS
        )}`,
    ].join('; ');
}
