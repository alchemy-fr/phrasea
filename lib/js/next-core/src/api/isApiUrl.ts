/**
 * Whether `url` (absolute, as sent) targets the origin of the API. The access
 * token and the API-only headers must never leave for another host (S3,
 * presigned URLs, third-party services).
 */
export function isApiUrl(url: string, apiUrl: string): boolean {
    try {
        return (
            new URL(url).origin ===
            new URL(apiUrl, globalThis.location?.href).origin
        );
    } catch {
        return false;
    }
}
