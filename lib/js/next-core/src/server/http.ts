import 'server-only';
import http from 'node:http';
import https from 'node:https';

export type HttpGetOptions = {
    headers: Record<string, string>;
    rejectUnauthorized: boolean;
    /** In milliseconds, 10 seconds by default */
    timeout?: number;
};

export type HttpGet = (
    url: string,
    options: HttpGetOptions
) => Promise<{status: number; body: string}>;

export type HttpGetBufferOptions = HttpGetOptions & {
    /** The request fails on a larger body, unbounded by default */
    maxBytes?: number;
};

export type HttpBufferResponse = {
    status: number;
    headers: http.IncomingHttpHeaders;
    body: Buffer;
};

export type HttpGetBuffer = (
    url: string,
    options: HttpGetBufferOptions
) => Promise<HttpBufferResponse>;

/**
 * A GET from the server. Not `fetch`: the certificate check must be
 * disabled on the stacks served with a self-signed one (`VERIFY_SSL=false`).
 */
export const httpGetBuffer: HttpGetBuffer = (
    url,
    {headers, rejectUnauthorized, timeout = 10_000, maxBytes}
) =>
    new Promise((resolve, reject) => {
        const client = url.startsWith('http://') ? http : https;
        const req = client.get(
            url,
            {headers, rejectUnauthorized, timeout},
            res => {
                const chunks: Buffer[] = [];
                let size = 0;
                res.on('data', (c: Buffer) => {
                    size += c.length;
                    if (maxBytes !== undefined && size > maxBytes) {
                        const error = new Error(
                            `response larger than ${maxBytes} bytes`
                        );
                        reject(error);
                        req.destroy(error);

                        return;
                    }
                    chunks.push(c);
                });
                res.on('end', () =>
                    resolve({
                        status: res.statusCode ?? 0,
                        headers: res.headers,
                        body: Buffer.concat(chunks),
                    })
                );
                res.on('error', reject);
            }
        );
        req.on('timeout', () => req.destroy(new Error('timeout')));
        req.on('error', reject);
    });

/** A GET from the server whose body is read as UTF-8 text */
export const httpGet: HttpGet = async (url, options) => {
    const {status, body} = await httpGetBuffer(url, options);

    return {status, body: body.toString('utf8')};
};

export function isFalse(value: string | undefined): boolean {
    return ['0', 'false', 'off', 'n', 'no'].includes(
        (value ?? '').toLowerCase()
    );
}

export function isTrue(value: string | undefined): boolean {
    return ['1', 'true', 'on', 'y', 'yes'].includes(
        (value ?? '').toLowerCase()
    );
}

/** Whether the certificates of the stack are checked (`VERIFY_SSL`) */
export function verifySslFromEnv(
    env: Record<string, string | undefined>
): boolean {
    return !isFalse(env.VERIFY_SSL);
}
