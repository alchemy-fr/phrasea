/**
 * Feature 1 — Global structure, navigation, authentication.
 */
import {databoxNextUrl, adminUsername} from '../lib/urls';
import {assetsUrl, login, loginThroughKeycloak, openSettingsMenu, visitAssets} from './lib/app';

describe('Navigation & authentication', () => {
    beforeEach(() => {
        cy.viewport(1400, 900);
    });

    it('renders the anonymous assets screen and signs in through Keycloak', () => {
        cy.visit(assetsUrl());
        cy.getBySel('topbar').should('be.visible');
        cy.getBySel('search-input').should('be.visible');
        cy.getBySel('sign-in').should('be.visible').click();

        loginThroughKeycloak();

        cy.url({timeout: 30000}).should('include', '/assets');
        cy.getBySel('user-menu', {timeout: 30000}).should('be.visible');
        cy.getBySel('sign-in').should('not.exist');
    });

    it('shows the user menu and signs out', () => {
        login();
        visitAssets();
        cy.getBySel('user-menu').click();
        cy.get('[role=menu]').should('contain', adminUsername);
        cy.menuItem('Sign out').click();
        cy.getBySel('sign-in', {timeout: 30000}).should('be.visible');
        // The session cache is now invalid: drop it for the following specs
        Cypress.session.clearAllSavedSessions();
    });

    it('toggles the left panel and switches its tabs', () => {
        login();
        visitAssets();
        cy.getBySel('left-panel').should('be.visible');
        cy.getBySel('toggle-left-panel').click();
        cy.getBySel('left-panel').should('not.exist');
        cy.getBySel('toggle-left-panel').click();
        cy.getBySel('left-panel').should('be.visible');

        cy.getBySel('left-panel').find('[role=tab][aria-label="Navigation"]').click();
        cy.contains('Workspaces & collections').should('be.visible');
        cy.getBySel('left-panel').find('[role=tab][aria-label="Baskets"]').click();
        cy.contains('label', 'Display archived').should('be.visible');
        cy.getBySel('left-panel').find('[role=tab][aria-label="Facets"]').click();
    });

    it('opens on the assets from the root URL', () => {
        login();
        cy.visit(`${databoxNextUrl}/`);
        cy.url({timeout: 20000}).should('match', /\/assets(\?|$)/);
        cy.getBySel('search-input').should('exist');
    });

    it('resolves notification deep links', () => {
        login();
        cy.visit(`${databoxNextUrl}/notification-uri?uri=${encodeURIComponent('/assets')}`);
        cy.url({timeout: 20000}).should('match', /\/assets(\?|$)/);
        cy.getBySel('search-input').should('exist');
    });

    it('shows a 404 page for unknown routes', () => {
        cy.visit(`${databoxNextUrl}/this-route-does-not-exist`, {failOnStatusCode: false});
        cy.contains('404').should('be.visible');
        cy.contains('Page not found').should('be.visible');
        cy.contains('a', 'Back to assets').click();
        cy.url().should('include', '/assets');
    });

    it('opens the settings menu with theme and language entries', () => {
        login();
        visitAssets();
        openSettingsMenu();
        cy.menuItem('Theme').should('be.visible');
        cy.menuItem('Language').should('be.visible');
        cy.menuItem('Display profile').should('be.visible');
        cy.menuItem('Operation tasks').should('be.visible');
        cy.get('body').type('{esc}');
    });

    it('keeps the anonymous user on public routes', () => {
        cy.visit(`${databoxNextUrl}/`);
        cy.getBySel('topbar').should('be.visible');
        cy.getBySel('sign-in').should('be.visible');
    });
});
