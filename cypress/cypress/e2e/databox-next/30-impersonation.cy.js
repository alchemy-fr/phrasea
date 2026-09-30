/**
 * Feature 30 — Admins switch to another user's account to test permissions.
 */
import {deleteWorkspace, ensureUser, seedWorkspace} from './lib/api';
import {login, visitWorkspace, waitForResults} from './lib/app';

describe('Impersonation', () => {
    let ctx;

    before(() => {
        login();
        ensureUser('alice');
        seedWorkspace({assets: 1}).then(c => {
            ctx = c;
        });
    });

    after(() => {
        if (ctx) {
            deleteWorkspace(ctx.workspace.id);
        }
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
    });

    afterEach(() => {
        cy.window().then(win => win.localStorage.removeItem('dbx.impersonation'));
    });

    it('browses with the permissions of another user, then switches back', () => {
        // The admin sees its private workspace
        visitWorkspace(ctx.workspace.id);
        waitForResults(1);
        cy.getBySel('impersonation-banner').should('not.exist');

        cy.getBySel('user-menu').click();
        cy.getBySel('switch-user').click();
        cy.getBySel('switch-user-dialog').within(() => {
            cy.get('[cmdk-input]').type('alice');
            cy.getBySel('switch-user-alice', {timeout: 20000}).click();
        });

        // The app reloads as alice, who has no access to the workspace
        cy.getBySel('impersonation-banner', {timeout: 30000}).should('contain', 'alice');
        cy.contains('No results', {timeout: 30000}).should('be.visible');
        cy.getBySel('asset-item').should('not.exist');

        // Nor to the admin screens
        cy.getBySel('settings-menu').click();
        cy.contains('[role=menuitem]', 'Operation tasks').should('not.exist');
        cy.get('body').type('{esc}');

        cy.getBySel('user-menu').click();
        cy.getBySel('impersonated-by').should('be.visible');
        cy.getBySel('switch-user').should('exist');
        cy.get('body').type('{esc}');

        cy.getBySel('impersonation-banner').contains('button', 'Back to my account').click();
        cy.getBySel('impersonation-banner').should('not.exist');
        waitForResults(1);
    });
});
