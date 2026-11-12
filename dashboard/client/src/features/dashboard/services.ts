import type {AppConfig, AppId, ToolId} from '@/lib/config/types';
import {appIds, toolIds} from '@/lib/config/types';
import {isAppAdmin, isRealmManager, Role} from './roles';

export type CardId = 'iam' | AppId | 'dashboard';

export type ActionId = 'master-admin' | 'admin' | 'api-doc';

export type ServiceAction = {id: ActionId; href: string};

export type ServiceCard = {
    id: CardId;
    /** Undefined when the application has no client: the card has no link */
    href?: string;
    actions: ServiceAction[];
    /** The application whose API status the card shows */
    statusOf?: AppId;
};

type Context = {
    config: Pick<AppConfig, 'apps' | 'keycloak' | 'clientUrl'>;
    /** Realm roles of the user, empty when anonymous */
    roles: readonly string[];
    authenticated: boolean;
    /** Displayed in the services menu of another client */
    embedded: boolean;
};

function consoleUrl(keycloakUrl: string, realm: string): string {
    return `${keycloakUrl}/admin/${realm}/console`;
}

/** The cards of the dashboard, in display order */
export function getServiceCards({
    config,
    roles,
    authenticated,
    embedded,
}: Context): ServiceCard[] {
    const cards: ServiceCard[] = [];
    const {keycloak} = config;
    const masterConsole = consoleUrl(keycloak.url, 'master');

    if (keycloak.url && (isRealmManager(roles) || roles.includes(Role.Admin))) {
        // An `admin` who does not manage the realm still reaches the master
        // console from here
        const realmManager = isRealmManager(roles);
        cards.push({
            id: 'iam',
            href: realmManager
                ? consoleUrl(keycloak.url, keycloak.realm)
                : masterConsole,
            actions:
                realmManager && roles.includes(Role.Admin)
                    ? [{id: 'master-admin', href: masterConsole}]
                    : [],
        });
    }

    for (const id of appIds) {
        const app = config.apps[id];
        if (!app) {
            continue;
        }
        const actions: ServiceAction[] = [];
        if (isAppAdmin(roles, id)) {
            actions.push({id: 'admin', href: `${app.apiUrl}/admin`});
        }
        if (authenticated) {
            actions.push({id: 'api-doc', href: app.apiUrl});
        }
        cards.push({id, href: app.clientUrl, actions, statusOf: id});
    }

    if (embedded) {
        cards.push({
            id: 'dashboard',
            href: config.clientUrl || '/',
            actions: [],
        });
    }

    return cards;
}

export type ToolLink = {id: ToolId; href: string};

/** The operation tools, for the `tech` role */
export function getTools(
    tools: AppConfig['tools'],
    roles: readonly string[]
): ToolLink[] {
    if (!roles.includes(Role.Tech)) {
        return [];
    }

    return toolIds.flatMap(id => {
        const href = tools[id];

        return href ? [{id, href}] : [];
    });
}
