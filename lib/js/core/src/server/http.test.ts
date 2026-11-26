// @vitest-environment node
import {afterAll, beforeAll, describe, expect, it} from 'vitest';
import http from 'node:http';
import type {AddressInfo} from 'node:net';
import {
    httpGet,
    httpGetBuffer,
    isFalse,
    isTrue,
    verifySslFromEnv,
} from './http';

let server: http.Server;
let base: string;

beforeAll(async () => {
    server = http.createServer((req, res) => {
        if (req.url === '/big') {
            res.writeHead(200, {'Content-Type': 'application/octet-stream'});
            res.end(Buffer.alloc(64 * 1024, 1));

            return;
        }
        res.writeHead(201, {
            'Content-Type': 'text/plain; charset=utf-8',
            'X-Echo': req.headers['x-test'] ?? '',
        });
        res.end('héllo');
    });
    await new Promise<void>(resolve => server.listen(0, '127.0.0.1', resolve));
    base = `http://127.0.0.1:${(server.address() as AddressInfo).port}`;
});

afterAll(() => {
    server.close();
});

describe('httpGetBuffer', () => {
    it('returns the status, the headers and the raw body', async () => {
        const res = await httpGetBuffer(`${base}/`, {
            headers: {'X-Test': 'yes'},
            rejectUnauthorized: true,
        });
        expect(res.status).toBe(201);
        expect(res.headers['content-type']).toBe('text/plain; charset=utf-8');
        expect(res.headers['x-echo']).toBe('yes');
        expect(Buffer.isBuffer(res.body)).toBe(true);
        expect(res.body.toString('utf8')).toBe('héllo');
    });

    it('fails beyond the size limit', async () => {
        await expect(
            httpGetBuffer(`${base}/big`, {
                headers: {},
                rejectUnauthorized: true,
                maxBytes: 1024,
            })
        ).rejects.toThrow('larger than 1024 bytes');

        const res = await httpGetBuffer(`${base}/big`, {
            headers: {},
            rejectUnauthorized: true,
            maxBytes: 64 * 1024,
        });
        expect(res.body.length).toBe(64 * 1024);
    });
});

describe('httpGet', () => {
    it('keeps its contract: status and UTF-8 text', async () => {
        await expect(
            httpGet(`${base}/`, {headers: {}, rejectUnauthorized: true})
        ).resolves.toEqual({status: 201, body: 'héllo'});
    });
});

describe('boolean env values', () => {
    it('reads true and false values', () => {
        expect(isTrue('Yes')).toBe(true);
        expect(isTrue('1')).toBe(true);
        expect(isTrue('')).toBe(false);
        expect(isFalse('off')).toBe(true);
        expect(isFalse(undefined)).toBe(false);
        expect(verifySslFromEnv({})).toBe(true);
        expect(verifySslFromEnv({VERIFY_SSL: 'false'})).toBe(false);
    });
});
