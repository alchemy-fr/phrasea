/**
 * Uploader API helpers used to seed and check test data. Requests are made
 * with the token of the user logged in the app (`login()` first): the default
 * admin is an admin of the Uploader API, whereas the `uploader-admin` service
 * account only gets the scopes Keycloak grants it, `admin` not included.
 */
import {keycloakRealm, keycloakUrl, uploaderApiUrl, uploaderClientId, uploaderUrl} from '../../lib/urls';

const tokenUrl = `${keycloakUrl}/realms/${keycloakRealm}/protocol/openid-connect/token`;
const STORAGE_KEY = 'upl.auth';

let cachedToken;

/**
 * Access token of the user logged in the app (refresh-token grant with the
 * public client), read from the app's localStorage; the app is loaded if
 * needed so that the session restored by `login()` is there.
 */
export function getAdminToken() {
    if (cachedToken && cachedToken.expiresAt > Date.now() + 30000) {
        return cy.wrap(cachedToken.value, {log: false});
    }

    return cy
        .window({log: false})
        .then(win => {
            if (!String(win.location.href).startsWith(uploaderUrl)) {
                cy.visit(uploaderUrl);
                cy.get('[data-testid=user-menu]', {timeout: 30000}).should('exist');
            }
        })
        .then(() => cy.window({log: false}))
        .then(win => {
            const raw = win.localStorage.getItem(STORAGE_KEY);
            expect(raw, 'stored refresh token').to.be.a('string');
            const {refreshToken} = JSON.parse(raw);

            return cy
                .request({
                    method: 'POST',
                    url: tokenUrl,
                    form: true,
                    body: {
                        grant_type: 'refresh_token',
                        client_id: uploaderClientId,
                        refresh_token: refreshToken,
                    },
                })
                .then(res => {
                    if (res.body.refresh_token) {
                        win.localStorage.setItem(
                            STORAGE_KEY,
                            JSON.stringify({
                                refreshToken: res.body.refresh_token,
                                refreshExpiresAt: res.body.refresh_expires_in
                                    ? Math.floor(Date.now() / 1000) + res.body.refresh_expires_in
                                    : undefined,
                            })
                        );
                    }
                    cachedToken = {value: res.body.access_token, expiresAt: Date.now() + 4 * 60 * 1000};

                    return cachedToken.value;
                });
        });
}

/**
 * A request to the Uploader API: JSON-LD by default, plain JSON with `json`
 * (the endpoints answering a bare value).
 */
export function apiRequest({method = 'GET', path, body, qs, failOnStatusCode = true, json = false}) {
    const contentType = json ? 'application/json' : method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json';

    return getAdminToken().then(token =>
        cy
            .request({
                method,
                url: path.startsWith('http') ? path : `${uploaderApiUrl}${path}`,
                body,
                qs,
                failOnStatusCode,
                headers: {
                    Accept: json ? 'application/json' : 'application/ld+json',
                    'Content-Type': contentType,
                },
                auth: {bearer: token},
            })
            .then(res => res.body)
    );
}

/** Every item of a Hydra collection, following its pages */
export function fetchAll(path, qs) {
    const items = [];
    const fetchPage = (p, params) =>
        apiRequest({path: p, qs: params}).then(body => {
            items.push(...(body.member ?? []));
            const next = body.view?.next;

            return next ? fetchPage(next) : items;
        });

    return fetchPage(path, qs);
}

export const iri = (resource, id) => `/${resource}/${id}`;

export function uniqueValue(prefix) {
    return `${prefix}-${Date.now().toString(36)}${Math.floor(Math.random() * 1000)}`;
}

/**
 * The target of a spec, created on its first run and reset on the next ones:
 * a target holding commits cannot be deleted. Without `targetUrl` it is in
 * pull mode: commits are stored, never pushed anywhere.
 */
export function ensureTarget({slug, ...data}) {
    const body = {
        slug,
        targetUrl: null,
        authorizationKey: null,
        defaultDestination: null,
        allowedGroups: null,
        description: null,
        ...data,
    };

    return fetchAll('/targets', {query: data.name}).then(targets => {
        const existing = targets.find(t => t.slug === slug);

        return existing
            ? apiRequest({method: 'PATCH', path: iri('targets', existing.id), body})
            : apiRequest({method: 'POST', path: '/targets', body});
    });
}

/** The form of a target (a form of the builder), null without one */
export function getForm(targetId) {
    return apiRequest({path: `/targets/${targetId}/form`, json: true});
}

/** Replaces the form of a target with a form of the builder holding `fields` */
export function setBuilderForm(targetId, fields) {
    return apiRequest({
        method: 'PUT',
        path: `/targets/${targetId}/form`,
        body: {form: {version: 1, workspaceId: null, fields}},
        json: true,
    });
}

export function removeForm(targetId) {
    return apiRequest({method: 'PUT', path: `/targets/${targetId}/form`, body: {form: null}, json: true});
}

/** The data of a target, merged into the form data of its commits; null without any */
export function getTargetData(targetId) {
    return apiRequest({path: `/targets/${targetId}/data`, json: true});
}

export function setTargetData(targetId, data) {
    return apiRequest({method: 'PUT', path: `/targets/${targetId}/data`, body: {data}, json: true});
}

export function removeTargetData(targetId) {
    return setTargetData(targetId, null);
}

/**
 * Waits for the commit of a target matching `predicate` (the commits are
 * persisted by a worker, out of the request outside of the dev environment).
 */
export function waitForCommit(targetId, predicate, message = 'commit') {
    let found;

    return cy
        .waitUntil(
            () =>
                fetchAll('/commits', {target: targetId}).then(commits => {
                    found = commits.find(predicate);

                    return !!found;
                }),
            {timeout: 60000, message}
        )
        .then(() => found);
}

/** The targets of the specs, visible to every user (no `allowedGroups`) */
export const TARGETS = {
    plain: {slug: 'e2e-upload', name: 'E2E Upload', description: 'Files sent without any form'},
    form: {slug: 'e2e-form', name: 'E2E Form', description: 'Files sent with their information'},
    admin: {slug: 'e2e-admin', name: 'E2E Admin'},
};

/**
 * A builder form: a required text, a bounded number and the notification
 * choice, as the editor adds them.
 */
export const FORM_FIELDS = [
    {id: 'about', source: 'layout', kind: 'section', label: {_: 'About the files'}},
    {
        id: 'album',
        source: 'custom',
        key: 'album',
        type: 'text',
        required: true,
        label: {_: 'Album'},
        constraints: {maxLength: 40},
    },
    {id: 'copies', source: 'custom', key: 'copies', type: 'number', label: {_: 'Copies'}, constraints: {max: 5}},
    {
        id: 'notify',
        source: 'builtin',
        key: '__notify',
        type: 'boolean',
        label: {en: 'Notify me when done', fr: 'Me prévenir à la fin du traitement'},
    },
];
