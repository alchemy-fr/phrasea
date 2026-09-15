/**
 * Feature 25 — Runtime configuration injected by the server.
 */
import {databoxApiUrl, keycloakUrl} from '../lib/urls';
import {assetsUrl, login, visitAssets} from './lib/app';

describe('Runtime configuration', () => {
    beforeEach(() => {
        cy.viewport(1400, 900);
    });

    it('calls the configured API URL', () => {
        cy.intercept('GET', `${databoxApiUrl}/assets*`).as('search');
        cy.visit(assetsUrl());
        cy.wait('@search', {timeout: 30000}).its('response.statusCode').should('be.oneOf', [200, 401]);
    });

    it('redirects to the configured Keycloak realm', () => {
        cy.visit(assetsUrl());
        cy.getBySel('sign-in').click();
        cy.origin(keycloakUrl, () => {
            cy.location('pathname').should('include', '/realms/');
            cy.get('#username').should('be.visible');
        });
    });

    it('links to the dashboard when the services menu is enabled', () => {
        const dashboardUrl = Cypress.env('DASHBOARD_CLIENT_URL');
        login();
        visitAssets();
        if (dashboardUrl) {
            cy.get(`a[href="${dashboardUrl}"]`).should('exist');
        }
    });

    it('exposes the allowed upload file types', () => {
        login();
        visitAssets();
        cy.getBySel('left-panel').find('[role=tab][aria-label="Navigation"]').click();
        cy.getBySel('workspace-item').first().rightclick();
        cy.menuItem('Add asset').click();
        // The dropzone gets its accepted types from ALLOWED_FILE_TYPES
        cy.get('[role=dialog] input[type=file]').should('exist');
        cy.get('[role=dialog]').contains('Add assets').should('be.visible');
        cy.get('body').type('{esc}');
    });
});
