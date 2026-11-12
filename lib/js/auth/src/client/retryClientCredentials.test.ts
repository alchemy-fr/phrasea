import {
    configureClientAuthentication,
    configureClientCredentials401Retry,
    OAuthClient,
} from './OAuthClient';
import {GrantTypeRefreshMethod} from '../types';
import AxiosMockAdapter from 'axios-mock-adapter';
import {createHttpClient} from '@alchemy/api';
import {MemoryStorage} from '@alchemy/storage';

test('configureClientCredentials401Retry', async () => {
    const baseUrl = 'http://localhost';
    const httpClient = createHttpClient(baseUrl);

    const oauthClient = new OAuthClient({
        baseUrl,
        clientId: 'test',
        httpClient,
    });

    let tokenVersion = 1;
    configureClientAuthentication(httpClient, oauthClient);

    const mock = new AxiosMockAdapter(httpClient, {
        onNoMatch: 'throwException',
    });

    mock.onGet('/public').reply(200, 42);
    mock.onGet('/private').reply(config => {
        const match = config.headers.Authorization?.match(/^Bearer (\d+)$/);
        if (match) {
            if (match[1] === '2') {
                return [401];
            }

            return [200, 42];
        }

        return [401];
    });
    mock.onPost('/token').reply(200, {
        // eslint-disable-next-line no-useless-assignment
        access_token: (tokenVersion++).toString(),
        expires_in: 42000,
        token_type: 'Bearer',
    });

    await expect(
        httpClient
            .request({
                method: 'GET',
                url: '/public',
            })
            .then(r => r.data)
    ).resolves.toBe(42);

    await expect(
        httpClient.request({
            method: 'GET',
            url: '/private',
        })
        // toThrow matches on the message only: the rejection is an AxiosError,
        // which vitest's toEqual would compare field by field (config, response…).
    ).rejects.toThrow('Request failed with status code 401');

    configureClientCredentials401Retry(httpClient, oauthClient);

    await expect(
        httpClient
            .request({
                method: 'GET',
                url: '/public',
            })
            .then(r => r.data)
    ).resolves.toBe(42);

    await expect(
        httpClient
            .request({
                method: 'GET',
                url: '/private',
            })
            .then(r => r.data)
    ).resolves.toBe(42);

    await oauthClient.logout();

    await expect(
        httpClient
            .request({
                method: 'GET',
                url: '/private',
            })
            .then(r => r.data)
    ).resolves.toBe(42);
});

test('the access token is only sent to the origin of the client', async () => {
    const baseUrl = 'http://localhost';
    const httpClient = createHttpClient(baseUrl);
    const authHttpClient = createHttpClient(baseUrl);

    const oauthClient = new OAuthClient({
        baseUrl,
        clientId: 'test',
        clientSecret: 'secret',
        httpClient: authHttpClient,
        storage: new MemoryStorage(),
    });

    configureClientAuthentication(
        httpClient,
        oauthClient,
        GrantTypeRefreshMethod.clientCredentials
    );
    configureClientCredentials401Retry(httpClient, oauthClient);

    const mock = new AxiosMockAdapter(httpClient, {
        onNoMatch: 'throwException',
    });
    const authMock = new AxiosMockAdapter(authHttpClient, {
        onNoMatch: 'throwException',
    });

    let tokenRequests = 0;
    authMock.onPost('/token').reply(() => {
        tokenRequests++;

        return [
            200,
            {access_token: 'secret', expires_in: 42000, token_type: 'Bearer'},
        ];
    });

    const authorizations: (string | undefined)[] = [];
    mock.onGet('https://s3.test/file').reply(config => {
        authorizations.push(config.headers?.Authorization);

        return [401];
    });
    mock.onGet('/private').reply(config => {
        authorizations.push(config.headers?.Authorization);

        return [200, 42];
    });

    await expect(
        httpClient.request({method: 'GET', url: 'https://s3.test/file'})
    ).rejects.toThrow('Request failed with status code 401');
    expect(authorizations).toEqual([undefined]);
    expect(tokenRequests).toBe(0);

    await expect(
        httpClient.request({method: 'GET', url: '/private'}).then(r => r.data)
    ).resolves.toBe(42);
    expect(authorizations).toEqual([undefined, 'Bearer secret']);
    expect(tokenRequests).toBe(1);
});
