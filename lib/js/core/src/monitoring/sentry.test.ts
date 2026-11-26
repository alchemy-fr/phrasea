import {createElement} from 'react';
import {render} from '@testing-library/react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import * as Sentry from '@sentry/react';
import type {Breadcrumb, ErrorEvent} from '@sentry/react';
import {
    initSentry,
    redactBreadcrumb,
    redactEvent,
    resetSentryForTests,
    SENTRY_TUNNEL_DSN,
    SENTRY_TUNNEL_PATH,
    setSentryUser,
} from './sentry';
import {SentryInit} from './SentryInit';
import {SentryUser} from './SentryUser';
import {createUrlRedactor} from '../privacy/redactUrl';

vi.mock('@sentry/react', () => ({
    init: vi.fn(),
    setTag: vi.fn(),
    setUser: vi.fn(),
}));

const config = {
    environment: 'prod',
    release: '1.2.3',
};
const app = {appId: 'databox-1', appName: 'databox'};
const redactUrl = createUrlRedactor([
    {pattern: /^\/l\/[^/?#]+/, replacement: '/l/[token]'},
]);

function initOptions() {
    const options = vi.mocked(Sentry.init).mock.calls[0][0];

    return {
        beforeSend: options?.beforeSend as (e: ErrorEvent) => ErrorEvent,
        beforeBreadcrumb: options?.beforeBreadcrumb as (
            b: Breadcrumb
        ) => Breadcrumb,
    };
}

beforeEach(() => {
    resetSentryForTests();
    vi.clearAllMocks();
});

describe('sentry', () => {
    it('does nothing before the initialization', () => {
        setSentryUser({id: 'u1'});

        expect(Sentry.setUser).not.toHaveBeenCalled();
    });

    it('sends the errors through the tunnel', () => {
        initSentry(config, app);

        expect(Sentry.init).toHaveBeenCalledTimes(1);
        const options = vi.mocked(Sentry.init).mock.calls[0][0];
        expect(SENTRY_TUNNEL_DSN).toBe(
            'https://public@sentry-tunnel.invalid/0'
        );
        expect(options).toMatchObject({
            dsn: SENTRY_TUNNEL_DSN,
            environment: 'prod',
            release: '1.2.3',
            tunnel: '/api/relay',
            dataCollection: {userInfo: false},
            enhanceFetchErrorMessages: false,
        });
        expect(SENTRY_TUNNEL_PATH).toBe('/api/relay');
        expect(options?.ignoreErrors?.length).toBeGreaterThan(0);
        expect(options?.denyUrls?.length).toBeGreaterThan(0);
        expect(options).not.toHaveProperty('tracesSampleRate');
        expect(typeof options?.integrations).toBe('function');
        const integrations = options?.integrations as (
            defaults: {name: string}[]
        ) => {name: string}[];
        expect(
            integrations([{name: 'GlobalHandlers'}, {name: 'BrowserSession'}])
        ).toEqual([{name: 'GlobalHandlers'}]);
        expect(Sentry.setTag).toHaveBeenCalledWith('app.name', 'databox');
        expect(Sentry.setTag).toHaveBeenCalledWith('app.id', 'databox-1');
    });

    it('is initialized once', () => {
        initSentry(config, app);
        initSentry(config, app);

        expect(Sentry.init).toHaveBeenCalledTimes(1);
    });

    it('sets and clears the user', () => {
        initSentry(config, app);
        setSentryUser({id: 'u1', username: 'jdoe', email: 'j@doe.test'});
        setSentryUser(undefined);

        expect(vi.mocked(Sentry.setUser).mock.calls).toEqual([
            [{id: 'u1', username: 'jdoe', email: 'j@doe.test'}],
            [null],
        ]);
    });
});

describe('redactEvent', () => {
    it('masks the URLs of the event and drops the credentials', () => {
        const event: ErrorEvent = {
            type: undefined,
            transaction: '/l/secret',
            request: {
                url: 'https://uploader.test/l/secret?lang=fr',
                headers: {
                    'Referer': 'https://uploader.test/l/secret',
                    'User-Agent': 'Firefox',
                    'Authorization': 'UploadLink secret',
                    'cookie': 'session=secret',
                },
            },
            exception: {
                values: [
                    {
                        type: 'Error',
                        stacktrace: {
                            frames: [
                                {
                                    filename: 'https://uploader.test/l/secret',
                                    abs_path: 'https://uploader.test/l/secret',
                                },
                                {filename: 'app:///_next/static/chunks/a.js'},
                            ],
                        },
                    },
                ],
            },
            breadcrumbs: [
                {
                    category: 'navigation',
                    data: {from: '/', to: '/l/secret'},
                },
            ],
        };

        expect(redactEvent(event, redactUrl)).toEqual({
            type: undefined,
            transaction: '/l/[token]',
            request: {
                url: 'https://uploader.test/l/[token]?lang=fr',
                headers: {
                    'Referer': 'https://uploader.test/l/[token]',
                    'User-Agent': 'Firefox',
                },
            },
            exception: {
                values: [
                    {
                        type: 'Error',
                        stacktrace: {
                            frames: [
                                {
                                    filename: 'https://uploader.test/l/[token]',
                                    abs_path: 'https://uploader.test/l/[token]',
                                },
                                {filename: 'app:///_next/static/chunks/a.js'},
                            ],
                        },
                    },
                ],
            },
            breadcrumbs: [
                {
                    category: 'navigation',
                    data: {from: '/', to: '/l/[token]'},
                },
            ],
        });
    });

    it('keeps an event without URL', () => {
        const event: ErrorEvent = {type: undefined, message: 'boom'};

        expect(redactEvent(event, redactUrl)).toEqual({
            type: undefined,
            message: 'boom',
        });
    });
});

describe('redactBreadcrumb', () => {
    it('masks the URLs of the navigation and of the requests', () => {
        expect(
            redactBreadcrumb(
                {
                    category: 'navigation',
                    data: {
                        from: 'https://uploader.test/l/secret',
                        to: '/l/other',
                    },
                },
                redactUrl
            )
        ).toEqual({
            category: 'navigation',
            data: {from: 'https://uploader.test/l/[token]', to: '/l/[token]'},
        });
        expect(
            redactBreadcrumb(
                {
                    category: 'fetch',
                    type: 'http',
                    data: {
                        method: 'GET',
                        url: 'https://uploader.test/l/secret',
                        status_code: 200,
                    },
                },
                redactUrl
            )
        ).toEqual({
            category: 'fetch',
            type: 'http',
            data: {
                method: 'GET',
                url: 'https://uploader.test/l/[token]',
                status_code: 200,
            },
        });
    });

    it('keeps the breadcrumbs without data', () => {
        const breadcrumb = {category: 'console', message: '/l/secret'};

        expect(redactBreadcrumb(breadcrumb, redactUrl)).toBe(breadcrumb);
    });
});

describe('initSentry redaction', () => {
    it('masks the URLs of the client', () => {
        initSentry(config, {...app, redactUrl});
        const {beforeSend, beforeBreadcrumb} = initOptions();

        expect(
            beforeSend({
                type: undefined,
                request: {url: 'https://uploader.test/l/secret'},
            }).request?.url
        ).toBe('https://uploader.test/l/[token]');
        expect(
            beforeBreadcrumb({
                category: 'xhr',
                data: {url: '/l/secret'},
            }).data
        ).toEqual({url: '/l/[token]'});
    });

    it('masks the common URLs by default', () => {
        initSentry(config, app);
        const {beforeSend, beforeBreadcrumb} = initOptions();

        expect(
            beforeSend({
                type: undefined,
                request: {url: 'https://app.test/auth/callback?code=secret'},
            }).request?.url
        ).toBe('https://app.test/auth/callback');
        expect(
            beforeBreadcrumb({
                category: 'navigation',
                data: {from: '/auth/callback?code=secret', to: '/'},
            }).data
        ).toEqual({from: '/auth/callback', to: '/'});
    });
});

describe('SentryInit', () => {
    it('initializes the SDK when the stack configures Sentry', () => {
        render(createElement(SentryInit, {sentry: config, ...app}));

        expect(Sentry.init).toHaveBeenCalledTimes(1);
    });

    it('gives the redaction of the client to the SDK', () => {
        render(createElement(SentryInit, {sentry: config, ...app, redactUrl}));

        expect(
            initOptions().beforeBreadcrumb({
                category: 'navigation',
                data: {to: '/l/secret'},
            }).data
        ).toEqual({to: '/l/[token]'});
    });

    it('does nothing otherwise', () => {
        render(createElement(SentryInit, {sentry: undefined, ...app}));

        expect(Sentry.init).not.toHaveBeenCalled();
    });
});

describe('SentryUser', () => {
    const user = {id: 'u1', username: 'jdoe', email: 'j@doe.test'};

    it('waits for the session to be resolved', () => {
        initSentry(config, app);
        const {rerender} = render(
            createElement(SentryUser, {user: undefined, authResolved: false})
        );
        expect(Sentry.setUser).not.toHaveBeenCalled();

        rerender(createElement(SentryUser, {user, authResolved: true}));
        expect(Sentry.setUser).toHaveBeenLastCalledWith(user);

        rerender(
            createElement(SentryUser, {user: undefined, authResolved: true})
        );
        expect(Sentry.setUser).toHaveBeenLastCalledWith(null);
    });
});
