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

/**
 * A GET from the server. Not `fetch`: the certificate check must be
 * disabled on the stacks served with a self-signed one (`VERIFY_SSL=false`).
 */
export const httpGet: HttpGet = (
    url,
    {headers, rejectUnauthorized, timeout = 10_000}
) =>
    new Promise((resolve, reject) => {
        const client = url.startsWith('http://') ? http : https;
        const req = client.get(
            url,
            {headers, rejectUnauthorized, timeout},
            res => {
                const chunks: Buffer[] = [];
                res.on('data', (c: Buffer) => chunks.push(c));
                res.on('end', () =>
                    resolve({
                        status: res.statusCode ?? 0,
                        body: Buffer.concat(chunks).toString('utf8'),
                    })
                );
                res.on('error', reject);
            }
        );
        req.on('timeout', () => req.destroy(new Error('timeout')));
        req.on('error', reject);
    });

export function isFalse(value: string | undefined): boolean {
    return ['0', 'false', 'off', 'n', 'no'].includes(
        (value ?? '').toLowerCase()
    );
}
