import {AxiosError, InternalAxiosRequestConfig} from 'axios';
import {createHttpClient} from '../../../src/lib/axios';

// One failure, then success: tells whether the client replayed the request.
const flakyAdapter = (
    makeError: (config: InternalAxiosRequestConfig) => AxiosError
) => {
    let calls = 0;
    const adapter = async (config: InternalAxiosRequestConfig) => {
        calls++;
        if (calls === 1) {
            throw makeError(config);
        }

        return {data: {}, status: 200, statusText: 'OK', headers: {}, config};
    };

    return {adapter, calls: () => calls};
};

const networkError = (code: string) => (config: InternalAxiosRequestConfig) =>
    new AxiosError('boom', code, config);

const request = async (
    method: string,
    makeError: (config: InternalAxiosRequestConfig) => AxiosError
) => {
    const {adapter, calls} = flakyAdapter(makeError);
    const client = createHttpClient({
        baseURL: 'http://api.test',
        retries: 1,
        adapter,
    });

    try {
        await client.request({method, url: '/x'});
    } catch (_e) {
        // Asserted through the call count.
    }

    return calls();
};

describe('createHttpClient retry', () => {
    it('replays a GET that timed out', async () => {
        expect(await request('get', networkError('ECONNABORTED'))).toEqual(2);
    });

    it('replays a GET whose connection dropped', async () => {
        expect(await request('get', networkError('ECONNRESET'))).toEqual(2);
    });

    it('does not replay a POST that timed out', async () => {
        expect(await request('post', networkError('ECONNABORTED'))).toEqual(1);
    });

    it('does not replay a POST whose connection dropped', async () => {
        expect(await request('post', networkError('ECONNRESET'))).toEqual(1);
    });

    it('replays a POST that never reached the server', async () => {
        expect(await request('post', networkError('ECONNREFUSED'))).toEqual(2);
    });

    it('does not replay a POST answered with 503', async () => {
        expect(
            await request(
                'post',
                config =>
                    new AxiosError(
                        'unavailable',
                        'ERR_BAD_RESPONSE',
                        config,
                        undefined,
                        {
                            status: 503,
                            statusText: 'Service Unavailable',
                            data: {},
                            headers: {},
                            config,
                        }
                    )
            )
        ).toEqual(1);
    });

    it('does not replay a GET answered with 404', async () => {
        expect(
            await request(
                'get',
                config =>
                    new AxiosError(
                        'not found',
                        'ERR_BAD_REQUEST',
                        config,
                        undefined,
                        {
                            status: 404,
                            statusText: 'Not Found',
                            data: {},
                            headers: {},
                            config,
                        }
                    )
            )
        ).toEqual(1);
    });
});
