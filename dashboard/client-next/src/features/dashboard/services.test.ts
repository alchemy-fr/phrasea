import {describe, expect, it} from 'vitest';
import {getServiceCards, getTools} from './services';
import type {AppConfig} from '@/lib/config/types';

const config: Pick<AppConfig, 'apps' | 'keycloak' | 'clientUrl'> = {
    clientUrl: 'https://dashboard.test',
    keycloak: {
        url: 'https://keycloak.test',
        realm: 'phrasea',
        clientId: 'dashboard-app',
    },
    apps: {
        databox: {
            apiUrl: 'https://api-databox.test',
            clientUrl: 'https://databox.test',
        },
        uploader: {apiUrl: 'https://api-uploader.test'},
    },
};

const anonymous = {roles: [], authenticated: false, embedded: false};

function cards(ctx: Partial<Parameters<typeof getServiceCards>[0]> = {}) {
    return getServiceCards({config, ...anonymous, ...ctx});
}

describe('getServiceCards', () => {
    it('shows the deployed applications to anonymous visitors, without actions', () => {
        expect(cards()).toEqual([
            {
                id: 'databox',
                href: 'https://databox.test',
                actions: [],
                statusOf: 'databox',
            },
            {
                id: 'uploader',
                href: undefined,
                actions: [],
                statusOf: 'uploader',
            },
        ]);
    });

    it('offers the API documentation to signed-in users only', () => {
        expect(
            cards({authenticated: true, roles: []}).map(c => c.actions)
        ).toEqual([
            [{id: 'api-doc', href: 'https://api-databox.test'}],
            [{id: 'api-doc', href: 'https://api-uploader.test'}],
        ]);
    });

    it('offers the admin like the APIs grant it', () => {
        const admin = (roles: string[]) =>
            cards({authenticated: true, roles})
                .filter(c => c.actions.some(a => a.id === 'admin'))
                .map(c => c.id);

        expect(admin(['databox-admin'])).toEqual(['databox']);
        expect(admin(['expose-admin'])).toEqual([]);
        expect(admin(['admin'])).toEqual(['databox', 'uploader']);
    });

    it('shows the identity manager to the realm managers', () => {
        expect(cards({roles: ['user-admin']})[0]).toEqual({
            id: 'iam',
            href: 'https://keycloak.test/admin/phrasea/console',
            actions: [],
        });
        expect(cards({roles: ['group-admin', 'admin']})[0].actions).toEqual([
            {
                id: 'master-admin',
                href: 'https://keycloak.test/admin/master/console',
            },
        ]);
    });

    it('opens the master console to an admin who does not manage the realm', () => {
        expect(cards({roles: ['admin']})[0]).toEqual({
            id: 'iam',
            href: 'https://keycloak.test/admin/master/console',
            actions: [],
        });
    });

    it('adds the dashboard itself in the services menu', () => {
        expect(cards({embedded: true}).at(-1)).toEqual({
            id: 'dashboard',
            href: 'https://dashboard.test',
            actions: [],
        });
    });
});

describe('getTools', () => {
    const tools = {
        traefik: 'https://traefik.test',
        pgadmin: 'https://pgadmin.test',
    };

    it('lists the configured tools, in order, for the tech role', () => {
        expect(getTools(tools, ['tech'])).toEqual([
            {id: 'pgadmin', href: 'https://pgadmin.test'},
            {id: 'traefik', href: 'https://traefik.test'},
        ]);
        expect(getTools(tools, ['admin'])).toEqual([]);
    });
});
