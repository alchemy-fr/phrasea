/**
 * Databox API helpers used to seed and clean test data. Requests are made
 * with the `databox-admin` service account (client credentials), which has
 * the `databox-admin` role.
 */
import {
    databoxAdminClientId,
    databoxAdminClientSecret,
    databoxApiUrl,
    databoxNextClientId,
    databoxNextUrl,
    keycloakRealm,
    keycloakUrl,
} from '../../lib/urls';

const tokenUrl = `${keycloakUrl}/realms/${keycloakRealm}/protocol/openid-connect/token`;

let cachedToken;

export function getAdminToken() {
    if (cachedToken && cachedToken.expiresAt > Date.now() + 30000) {
        return cy.wrap(cachedToken.value, {log: false});
    }

    return cy
        .request({
            method: 'POST',
            url: tokenUrl,
            form: true,
            body: {
                grant_type: 'client_credentials',
                client_id: databoxAdminClientId,
                client_secret: databoxAdminClientSecret,
            },
        })
        .then(res => {
            expect(res.body).to.have.property('access_token');
            cachedToken = {
                value: res.body.access_token,
                expiresAt: Date.now() + res.body.expires_in * 1000,
            };

            return cachedToken.value;
        });
}

let cachedUserToken;

/**
 * Token used to seed data: the logged-in user's token (entities get a real
 * owner, unlike the service account which requires an explicit `ownerId`).
 * Requires a `login()` beforehand; the app is loaded if needed so that the
 * cached session storage is available.
 */
export function getSeedToken() {
    if (cachedUserToken && cachedUserToken.expiresAt > Date.now() + 30000) {
        return cy.wrap(cachedUserToken.value, {log: false});
    }

    return cy
        .window({log: false})
        .then(win => {
            if (!String(win.location.href).startsWith(databoxNextUrl)) {
                cy.visit(`${databoxNextUrl}/assets`);
                cy.get('[data-testid=user-menu]', {timeout: 30000}).should('exist');
            }
        })
        .then(() => getUserToken(databoxNextClientId))
        .then(token => {
            cachedUserToken = {value: token, expiresAt: Date.now() + 4 * 60 * 1000};

            return token;
        });
}

/**
 * Access token of the user currently logged in the app (refresh-token grant
 * with the public client), read from the app's localStorage.
 */
export function getUserToken(clientId) {
    return cy.window({log: false}).then(win => {
        const raw = win.localStorage.getItem('dbx.auth');
        expect(raw, 'stored refresh token').to.be.a('string');
        const {refreshToken} = JSON.parse(raw);

        return cy
            .request({
                method: 'POST',
                url: tokenUrl,
                form: true,
                body: {
                    grant_type: 'refresh_token',
                    client_id: clientId,
                    refresh_token: refreshToken,
                },
            })
            .then(res => {
                if (res.body.refresh_token) {
                    win.localStorage.setItem(
                        'dbx.auth',
                        JSON.stringify({
                            refreshToken: res.body.refresh_token,
                            refreshExpiresAt: res.body.refresh_expires_in
                                ? Math.floor(Date.now() / 1000) + res.body.refresh_expires_in
                                : undefined,
                        })
                    );
                }

                return res.body.access_token;
            });
    });
}

export function apiRequest({method = 'GET', path, body, token, qs, failOnStatusCode = true, headers = {}}) {
    const doRequest = t =>
        cy
            .request({
                method,
                url: `${databoxApiUrl}${path}`,
                body,
                qs,
                failOnStatusCode,
                headers: {
                    Accept: 'application/ld+json',
                    'Content-Type': method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json',
                    // Keep the stack quiet: no webhook / notification for seeded data
                    'X-Webhook-Disabled': 'true',
                    'X-Notification-Disabled': 'true',
                    ...headers,
                },
                auth: {bearer: t},
            })
            .then(res => res.body);

    return token ? doRequest(token) : getSeedToken().then(doRequest);
}

export const iri = (entity, id) => `/${entity}/${id}`;

/**
 * Distinct single-word names: the asset name is indexed for prefix matching,
 * so a query must start with the beginning of the name.
 */
export const ASSET_NAMES = ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot'];

export function uniqueName(prefix) {
    return `${prefix} ${Date.now().toString(36)}${Math.floor(Math.random() * 1000)}`;
}

export function createWorkspace(data = {}) {
    const name = data.name ?? uniqueName('E2E');

    return apiRequest({
        method: 'POST',
        path: '/workspaces',
        body: {
            name,
            slug: name.toLowerCase().replace(/[^a-z0-9]+/g, '-'),
            enabledLocales: ['en', 'fr'],
            ...data,
        },
    });
}

export function deleteWorkspace(id) {
    return apiRequest({method: 'DELETE', path: `/workspaces/${id}`, failOnStatusCode: false});
}

export function createCollection(workspaceId, data = {}) {
    return apiRequest({
        method: 'POST',
        path: '/collections',
        body: {workspace: iri('workspaces', workspaceId), ...data},
    });
}

export function createTag(workspaceId, name, color) {
    return apiRequest({
        method: 'POST',
        path: '/tags',
        body: {workspace: iri('workspaces', workspaceId), name, color},
    });
}

/**
 * Replicates the defaults the admin UI gives a new workspace
 * (`WorkspaceCreator`): a public rendition policy, the Main / Preview /
 * Thumbnail rendition chain and the metadata + rendition integrations,
 * without which uploads never get a thumbnail.
 */
export function seedWorkspaceDefaults(workspaceId) {
    const ctx = {};
    const ws = iri('workspaces', workspaceId);

    return apiRequest({
        method: 'POST',
        path: '/rendition-policies',
        body: {workspace: ws, name: 'Public', public: true, editable: true},
    })
        .then(policy => {
            ctx.renditionPolicy = policy;

            return apiRequest({
                method: 'POST',
                path: '/rendition-definitions',
                body: {workspace: ws, policy: iri('rendition-policies', policy.id), name: 'Main', key: 'main', buildMode: 1, useAsMain: true, substitutable: true, download: true},
            });
        })
        .then(main => {
            ctx.main = main;

            return cy.fixture('renditions/preview.yaml').then(definition =>
                apiRequest({
                    method: 'POST',
                    path: '/rendition-definitions',
                    body: {workspace: ws, policy: iri('rendition-policies', ctx.renditionPolicy.id), parent: iri('rendition-definitions', main.id), name: 'Preview', key: 'preview', buildMode: 2, definition, useAsPreview: true, substitutable: true, download: true},
                })
            );
        })
        .then(preview => {
            ctx.preview = preview;

            return cy.fixture('renditions/thumbnail.yaml').then(definition =>
                apiRequest({
                    method: 'POST',
                    path: '/rendition-definitions',
                    body: {workspace: ws, policy: iri('rendition-policies', ctx.renditionPolicy.id), parent: iri('rendition-definitions', preview.id), name: 'Thumbnail', key: 'thumbnail', buildMode: 2, definition, useAsThumbnail: true, substitutable: true, download: true},
                })
            );
        })
        .then(thumbnail => {
            ctx.thumbnail = thumbnail;

            return apiRequest({
                method: 'POST',
                path: '/integrations',
                body: {workspace: ws, integration: 'core.read_metadata', public: false, enabled: true},
            });
        })
        .then(() =>
            apiRequest({
                method: 'POST',
                path: '/integrations',
                body: {workspace: ws, integration: 'core.rendition', public: true, enabled: true},
            })
        )
        .then(() => ctx);
}

export function createAttributePolicy(workspaceId, data = {}) {
    return apiRequest({
        method: 'POST',
        path: '/attribute-policies',
        body: {
            workspace: iri('workspaces', workspaceId),
            name: 'Public',
            public: true,
            editable: true,
            ...data,
        },
    });
}

export function createAttributeDefinition(workspaceId, policyId, data = {}) {
    return apiRequest({
        method: 'POST',
        path: '/attribute-definitions',
        body: {
            workspace: iri('workspaces', workspaceId),
            policy: iri('attribute-policies', policyId),
            type: 'text',
            searchable: true,
            editable: true,
            editableInGui: true,
            enabled: true,
            ...data,
        },
    });
}

/**
 * Creates an asset without a file (fast, no worker involved).
 */
export function createAsset(workspaceId, data = {}) {
    return apiRequest({
        method: 'POST',
        path: '/assets',
        body: {workspace: iri('workspaces', workspaceId), ...data},
    });
}

export function getAsset(id) {
    return apiRequest({path: `/assets/${id}`});
}

/**
 * Uploads a fixture through the multipart flow (POST /uploads, PUT to the
 * presigned URL, POST /assets with `multipart`), exactly as the client does.
 */
export function uploadAssetFromFixture(workspaceId, fixture, data = {}, mime = 'image/png') {
    return cy.fixture(fixture, 'binary').then(binary => {
        const blob = Cypress.Blob.binaryStringToBlob(binary, mime);
        const filename = fixture.split('/').pop();

        return getSeedToken().then(token =>
            apiRequest({
                method: 'POST',
                path: '/uploads',
                token,
                body: {filename, type: mime, size: blob.size},
            }).then(init =>
                apiRequest({
                    method: 'POST',
                    path: `/uploads/${init.id}/part`,
                    token,
                    body: {part: 1},
                    // Plain controller: the JSON body is only decoded for `application/json`
                    headers: {'Content-Type': 'application/json'},
                }).then(({url}) =>
                    cy
                        .request({method: 'PUT', url, body: blob, headers: {'Content-Type': mime}})
                        .then(res => {
                            const etag = res.headers.etag;
                            expect(etag, 'ETag from storage').to.be.a('string');

                            return apiRequest({
                                method: 'POST',
                                path: '/assets',
                                token,
                                body: {
                                    workspace: iri('workspaces', workspaceId),
                                    name: filename,
                                    ...data,
                                    multipart: {
                                        uploadId: init.id,
                                        parts: [{ETag: etag, PartNumber: 1}],
                                    },
                                },
                            });
                        })
                )
            )
        );
    });
}

/**
 * Search assets (ES) — used to wait for indexation.
 */
export function searchAssets(qs) {
    return apiRequest({path: '/assets', qs});
}

export function waitForIndexed(qs, expectedCount, options = {}) {
    return cy.waitUntil(
        () => searchAssets(qs).then(body => body['hydra:totalItems'] >= expectedCount),
        {timeout: 90000, message: `${expectedCount} indexed asset(s) for ${JSON.stringify(qs)}`, ...options}
    );
}

export function waitForAsset(id, predicate, message = 'asset state') {
    return cy.waitUntil(() => getAsset(id).then(predicate), {timeout: 120000, message});
}

/**
 * Seeds a complete workspace: attribute policy + a "Description" text
 * attribute + a "Keywords" multi-valued facetable attribute, two tags, a
 * collection tree and a few assets. Returns a plain object usable across
 * tests of a spec.
 */
export function seedWorkspace({assets = 3, name} = {}) {
    const ctx = {};

    return createWorkspace(name ? {name} : {})
        .then(ws => {
            ctx.workspace = ws;

            return seedWorkspaceDefaults(ws.id);
        })
        .then(defaults => {
            ctx.renditions = defaults;

            return createAttributePolicy(ctx.workspace.id);
        })
        .then(policy => {
            ctx.policy = policy;

            // The asset "name" is stored in the attribute flagged `fillFromName`
            return createAttributeDefinition(ctx.workspace.id, policy.id, {
                name: 'Title',
                fillFromName: true,
                namePriority: 1,
                sortable: true,
            });
        })
        .then(def => {
            ctx.title = def;

            return createAttributeDefinition(ctx.workspace.id, ctx.policy.id, {name: 'Description', sortable: true});
        })
        .then(def => {
            ctx.description = def;

            return createAttributeDefinition(ctx.workspace.id, ctx.policy.id, {
                name: 'Keywords',
                multiple: true,
                facetEnabled: true,
                sortable: true,
                suggest: true,
            });
        })
        .then(def => {
            ctx.keywords = def;

            return createTag(ctx.workspace.id, 'online', '#7EF284');
        })
        .then(tag => {
            ctx.tagOnline = tag;

            return createTag(ctx.workspace.id, 'offline', '#FF0000');
        })
        .then(tag => {
            ctx.tagOffline = tag;

            return createCollection(ctx.workspace.id, {name: 'Sport'});
        })
        .then(c => {
            ctx.sport = c;

            return createCollection(ctx.workspace.id, {name: 'Football', parent: iri('collections', c.id)});
        })
        .then(c => {
            ctx.football = c;

            return createCollection(ctx.workspace.id, {name: 'Entertainment'});
        })
        .then(c => {
            ctx.entertainment = c;
            ctx.assets = [];
            const chain = [];
            for (let i = 1; i <= assets; i++) {
                chain.push(() =>
                    createAsset(ctx.workspace.id, {
                        name: ASSET_NAMES[i - 1],
                        collection: iri('collections', i % 2 === 0 ? ctx.football.id : ctx.sport.id),
                        tags: [iri('tags', i % 2 === 0 ? ctx.tagOnline.id : ctx.tagOffline.id)],
                        attributes: [
                            {definitionId: ctx.description.id, value: `${ASSET_NAMES[i - 1]} description`},
                            {definitionId: ctx.keywords.id, value: i % 2 === 0 ? 'even' : 'odd'},
                        ],
                    }).then(a => {
                        ctx.assets.push(a);
                    })
                );
            }

            return chain.reduce((prev, fn) => prev.then(fn), cy.wrap(null, {log: false}));
        })
        .then(() => waitForIndexed({'workspaces[]': ctx.workspace.id}, assets))
        .then(() => ctx);
}
