import {
    configureClientAuthentication,
    configureClientCredentials401Retry,
    OAuthClient,
} from './OAuthClient';
import AxiosMockAdapter from 'axios-mock-adapter';
import {createHttpClient} from '@alchemy/api';

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
    ).rejects.toEqual(new Error('Request failed with status code 401'));

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
