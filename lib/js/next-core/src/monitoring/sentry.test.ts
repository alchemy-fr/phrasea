import {createElement} from 'react';
import {render} from '@testing-library/react';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import * as Sentry from '@sentry/react';
import {
    captureException,
    initSentry,
    isSentryEnabled,
    resetSentryForTests,
    SENTRY_TUNNEL_PATH,
    setSentryUser,
} from './sentry';
import {SentryInit} from './SentryInit';
import {SentryUser} from './SentryUser';

vi.mock('@sentry/react', () => ({
    init: vi.fn(),
    setTag: vi.fn(),
    setUser: vi.fn(),
    captureException: vi.fn(),
}));

const config = {
    dsn: 'https://public@o1.ingest.sentry.io/42',
    environment: 'prod',
    release: '1.2.3',
};
const app = {appId: 'databox-1', appName: 'databox'};

beforeEach(() => {
    resetSentryForTests();
    vi.clearAllMocks();
});

describe('sentry', () => {
    it('does nothing before the initialization', () => {
        setSentryUser({id: 'u1'});
        captureException(new Error('x'));

        expect(isSentryEnabled()).toBe(false);
        expect(Sentry.setUser).not.toHaveBeenCalled();
        expect(Sentry.captureException).not.toHaveBeenCalled();
    });

    it('sends the errors through the tunnel', () => {
        initSentry(config, app);

        expect(isSentryEnabled()).toBe(true);
        expect(Sentry.init).toHaveBeenCalledTimes(1);
        const options = vi.mocked(Sentry.init).mock.calls[0][0];
        expect(options).toMatchObject({
            dsn: config.dsn,
            environment: 'prod',
            release: '1.2.3',
            tunnel: '/api/relay',
            dataCollection: {userInfo: false},
        });
        expect(SENTRY_TUNNEL_PATH).toBe('/api/relay');
        expect(options?.ignoreErrors?.length).toBeGreaterThan(0);
        expect(options?.denyUrls?.length).toBeGreaterThan(0);
        expect(options).not.toHaveProperty('tracesSampleRate');
        expect(options).not.toHaveProperty('integrations');
        expect(Sentry.setTag).toHaveBeenCalledWith('app.name', 'databox');
        expect(Sentry.setTag).toHaveBeenCalledWith('app.id', 'databox-1');
    });

    it('is initialized once', () => {
        initSentry(config, app);
        initSentry(config, app);

        expect(Sentry.init).toHaveBeenCalledTimes(1);
    });

    it('is not initialized without DSN', () => {
        initSentry({dsn: ''}, app);

        expect(isSentryEnabled()).toBe(false);
        expect(Sentry.init).not.toHaveBeenCalled();
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

    it('captures the exceptions', () => {
        initSentry(config, app);
        const error = new Error('x');
        captureException(error);

        expect(Sentry.captureException).toHaveBeenCalledWith(error);
    });
});

describe('SentryInit', () => {
    it('initializes the SDK when the stack configures Sentry', () => {
        render(createElement(SentryInit, {sentry: config, ...app}));

        expect(Sentry.init).toHaveBeenCalledTimes(1);
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
