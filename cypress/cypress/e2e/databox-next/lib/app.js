/**
 * UI helpers for the Next.js Databox client.
 */
import {adminPassword, adminUsername, databoxNextUrl, keycloakUrl} from '../../lib/urls';

export const assetsUrl = () => `${databoxNextUrl}/assets`;

export function loginThroughKeycloak(username = adminUsername, password = adminPassword) {
    cy.origin(keycloakUrl, {args: {username, password}}, ({username, password}) => {
        cy.get('#username').type(username);
        cy.get('#password').type(password, {log: false});
        cy.contains('Sign In').click();
    });
}

/**
 * Logs in once per user and caches the session (localStorage of the app +
 * Keycloak cookies) across specs.
 */
export function login(username = adminUsername, password = adminPassword) {
    cy.session(
        ['databox-next', username],
        () => {
            cy.visit(assetsUrl());
            cy.getBySel('sign-in').click();
            loginThroughKeycloak(username, password);
            cy.getBySel('user-menu', {timeout: 30000}).should('exist');
        },
        {
            cacheAcrossSpecs: true,
            validate: () => {
                cy.visit(assetsUrl());
                cy.getBySel('user-menu', {timeout: 30000}).should('exist');
            },
        }
    );
}

export function visitAssets(query = {}) {
    const qs = new URLSearchParams(query).toString();
    cy.visit(`${assetsUrl()}${qs ? `?${qs}` : ''}`);
    cy.getBySel('search-input').should('exist');
}

/**
 * Opens the search screen restricted to a workspace (built-in `@workspace`
 * condition) so that each spec only sees its own data.
 */
export function visitWorkspace(workspaceId, extra = {}) {
    visitAssets({
        f: `@workspace:@workspace = "${workspaceId}"`,
        ...extra,
    });
}

export function search(text) {
    cy.getBySel('search-input').clear();
    if (text) {
        cy.getBySel('search-input').type(text);
    }
    cy.getBySel('search-submit').click();
}

export function assetItems() {
    return cy.getBySel('asset-item');
}

export function assetItem(name) {
    return cy.getBySel('asset-item').contains(name).closest('[data-testid=asset-item]');
}

export function waitForResults(count) {
    if (count === undefined) {
        return cy.getBySel('asset-item', {timeout: 30000}).should('have.length.greaterThan', 0);
    }

    return cy.getBySel('asset-item', {timeout: 30000}).should('have.length', count);
}

export function openLeftPanelTab(tab) {
    cy.getBySel('left-panel').then($panel => {
        if ($panel.length === 0) {
            cy.getBySel('toggle-left-panel').click();
        }
    });
    cy.getBySel('left-panel').find(`[role=tab][aria-label="${tab}"]`).click();
}

export function treeWorkspace(workspaceId) {
    return cy.get(`[data-testid=workspace-item][data-workspace-id="${workspaceId}"]`);
}

export function expandTreeWorkspace(workspaceId) {
    treeWorkspace(workspaceId).then($ws => {
        if ($ws.find('[aria-label=Expand]').length) {
            cy.wrap($ws).find('[aria-label=Expand]').click();
        }
    });
}

export function treeCollection(collectionId) {
    return cy.get(`[data-testid=collection-item][data-collection-id="${collectionId}"]`);
}

export function openAssetContextMenu(name) {
    assetItem(name).rightclick();
    cy.get('[role=menu]').should('be.visible');
}

export function openAsset(name) {
    assetItem(name).dblclick();
    cy.getBySel('asset-view', {timeout: 30000}).should('be.visible');
}

/**
 * The routed dialog once its data is loaded: the dialog content remounts when
 * the entity resolves, a `.within()` taken before that would query a detached
 * element.
 */
export function routeDialog() {
    cy.getBySel('route-dialog', {timeout: 30000}).should('be.visible');
    cy.get('[data-testid=route-dialog] .animate-pulse', {timeout: 30000}).should('not.exist');
    cy.wait(400, {log: false});

    // Routed dialogs can stack (e.g. a workflow opened from the asset dialog):
    // the top-most one is the last in the DOM.
    return cy.getBySel('route-dialog').last().should('be.visible');
}

export function dialogTab(title) {
    // The top-most modal layer is the only one receiving pointer events
    routeDialog().should('have.css', 'pointer-events', 'auto');
    cy.getBySel('route-dialog').last().find('[role=tab]').contains(title).click();
}

export function openSettingsMenu() {
    cy.getBySel('settings-menu').click();
    cy.get('[role=menu]').should('be.visible');
}

export function expectToast(text) {
    cy.get('[data-sonner-toast], [data-sonner-toaster]', {timeout: 20000}).contains(text).should('exist');
}

export function pickTreeNode(kind, label) {
    // kind: 'workspace' | 'collection'
    cy.get(`[data-testid=tree-picker-${kind}]`).contains(label).click();
}

/**
 * Expands a workspace of a CollectionTreePicker so that its collections load.
 */
export function expandTreePickerWorkspace(label) {
    const chevron = () =>
        cy
            .get('[data-testid=tree-picker-workspace]')
            .contains(label)
            .closest('[data-testid=tree-picker-workspace]')
            .parent()
            .find('button')
            .first();
    chevron().click();
    cy.wait(1000);
    cy.document().then(doc => {
        if (doc.querySelectorAll('[data-testid=tree-picker-collection]').length === 0) {
            chevron().click();
        }
    });
    cy.get('[data-testid=tree-picker-collection]', {timeout: 20000}).should('exist');
}

/**
 * Asserts a toast (Sonner) with the given text/regexp shows up. Toasts live
 * outside the modal dialogs: query them directly.
 */
export function expectToastText(text) {
    const re = text instanceof RegExp ? text : new RegExp(text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

    return cy.get('[data-sonner-toast]', {timeout: 30000}).should($toasts => {
        expect($toasts.text(), 'toast').to.match(re);
    });
}
