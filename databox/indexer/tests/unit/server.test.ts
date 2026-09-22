import request from 'supertest';
import {Server} from 'http';
import {app, runServer} from '../../src/server';
import {createTestLogger, TestLogger} from '../helpers/logger';

let server: Server;
let logger: TestLogger;

beforeAll(() => {
    logger = createTestLogger();
    // Port 0 lets the OS pick a free one; the suite talks to `app` through
    // supertest, the listener is only started to exercise runServer itself.
    process.env.SERVER_PORT = '0';
    server = runServer(logger);
});

afterAll(async () => {
    await new Promise<void>(resolve => server.close(() => resolve()));
});

describe('runServer', () => {
    it('returns a listening server', () => {
        expect(server.listening).toBe(true);
    });

    it('logs the address it listens on', async () => {
        await new Promise(r => setImmediate(r));

        expect(logger.hasMessageMatching('Server: listening at')).toBe(true);
    });
});

describe('GET /assets parameter validation', () => {
    it('rejects a request without source', async () => {
        const res = await request(app).get('/assets').query({path: 'a/b.txt'});

        expect(res.status).toEqual(400);
        expect(res.body).toEqual({
            error: 'Bad Request',
            error_description: 'Missing "source" parameter',
        });
    });

    it('rejects a request without path', async () => {
        const res = await request(app)
            .get('/assets')
            .query({source: 'fs_served'});

        expect(res.status).toEqual(400);
        expect(res.body).toEqual({
            error: 'Bad Request',
            error_description: 'Missing "path" parameter',
        });
    });

    it('checks source before path', async () => {
        const res = await request(app).get('/assets');

        expect(res.body.error_description).toEqual(
            'Missing "source" parameter'
        );
    });
});

describe('GET /assets dispatch', () => {
    it('serves a file through the fs handler', async () => {
        const res = await request(app)
            .get('/assets')
            .query({source: 'fs_served', path: 'served/a/b.txt'});

        expect(res.status).toEqual(200);
        expect(res.text).toEqual('served content');
    });

    it('answers 404 for a path the handler cannot resolve', async () => {
        const res = await request(app)
            .get('/assets')
            .query({source: 'fs_served', path: 'served/missing.txt'});

        expect(res.status).toEqual(404);
        expect(res.body.error).toEqual('Not Found');
    });

    it('answers 500 for an unknown source', async () => {
        const res = await request(app)
            .get('/assets')
            .query({source: 'nope', path: 'a/b.txt'});

        expect(res.status).toEqual(500);
        expect(res.body).toEqual({
            error: 'Server Error',
            error_description: 'Error: Error: Unknown location nope',
        });
    });

    it('reuses the same handler across requests for one source', async () => {
        const first = await request(app)
            .get('/assets')
            .query({source: 'fs_served', path: 'served/a/b.txt'});
        const second = await request(app)
            .get('/assets')
            .query({source: 'fs_served', path: 'served/a/b.txt'});

        expect(first.status).toEqual(200);
        expect(second.status).toEqual(200);
    });
});
