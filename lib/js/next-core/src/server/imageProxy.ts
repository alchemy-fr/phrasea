import 'server-only';
import {httpGetBuffer, type HttpGetBuffer} from './http';

export const IMAGE_PROXY_MAX_BYTES = 5 * 1024 * 1024;

function notFound(): Response {
    return new Response(null, {
        status: 404,
        headers: {'X-Robots-Tag': 'noindex'},
    });
}

/**
 * Serves an image fetched from the storage under a stable URL of the client:
 * the URLs of the API are signed for about an hour, while the link previews
 * (Open Graph) are cached much longer by the robots. The URL must come from
 * an answer of the API, never from the request.
 */
export async function proxyImage(
    url: string,
    {
        verifySsl,
        get = httpGetBuffer,
        maxBytes = IMAGE_PROXY_MAX_BYTES,
        timeout = 5_000,
    }: {
        verifySsl: boolean;
        get?: HttpGetBuffer;
        maxBytes?: number;
        timeout?: number;
    }
): Promise<Response> {
    let res: Awaited<ReturnType<HttpGetBuffer>>;
    try {
        res = await get(url, {
            headers: {Accept: 'image/*'},
            rejectUnauthorized: verifySsl,
            timeout,
            maxBytes,
        });
    } catch {
        return notFound();
    }
    const contentType = String(res.headers['content-type'] ?? '')
        .split(';')[0]
        .trim()
        .toLowerCase();
    // An SVG could run scripts once served from the origin of the client,
    // and the link previews do not display it anyway
    if (
        res.status !== 200 ||
        !contentType.startsWith('image/') ||
        contentType.includes('svg')
    ) {
        return notFound();
    }

    return new Response(new Uint8Array(res.body), {
        status: 200,
        headers: {
            'Content-Type': contentType,
            'Content-Length': String(res.body.length),
            'Cache-Control': 'public, max-age=3600',
            'X-Content-Type-Options': 'nosniff',
            'X-Robots-Tag': 'noindex',
        },
    });
}
