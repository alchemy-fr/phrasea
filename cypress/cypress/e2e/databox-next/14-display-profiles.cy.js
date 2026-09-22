/**
 * Feature 14 — Display profiles.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {expectToastText, login, openSettingsMenu, routeDialog, visitWorkspace, waitForResults} from './lib/app';

describe('Display profiles', () => {
    let ctx;
    const profileName = `E2E profile ${Date.now()}`;

    before(() => {
        login();
        seedWorkspace({assets: 2}).then(c => {
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
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
    });

    it('creates a profile and selects it', () => {
        openSettingsMenu();
        cy.menuItem('Display profile').click();
        cy.dialog().within(() => {
            cy.contains('Default display profile').should('be.visible');
            cy.contains('button', 'New profile').click();
        });
        cy.dialog('last').within(() => {
            cy.fieldByLabel('Name').type(profileName);
            cy.contains('button', 'Create').click();
        });
        cy.dialog().contains(profileName, {timeout: 20000}).should('exist');
        cy.wait(500);
        cy.dialog().contains(profileName).click();
        cy.get('body').type('{esc}');
        openSettingsMenu();
        cy.get('[role=menu]').should('contain', profileName);
        cy.get('body').type('{esc}');
    });

    it('organizes the displayed attributes and the grid card', () => {
        openSettingsMenu();
        cy.menuItem('Display profile').click();
        cy.dialog().contains(profileName).closest('li, div').find('button[aria-haspopup], button').last().click();
        cy.menuItem('Edit').click();
        routeDialog().within(() => {
            cy.contains('Display profile').should('be.visible');
            cy.contains('[role=tab]', 'Organize').click();
            cy.contains('Available attributes').should('be.visible');
            cy.contains('Description').parent().find('button').first().click();
            cy.contains('Displayed').should('be.visible');
        });
        routeDialog().within(() => {
            cy.contains('[role=tab]', 'Grid card').click();
            cy.contains('Card layout').should('be.visible');
        });
    });

    it('shows the pinned attributes in the viewer', () => {
        cy.getBySel('asset-item').first().dblclick();
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('Description').should('be.visible');
            cy.contains('description').should('be.visible');
        });
        cy.get('body').type('{esc}');
    });

    it('deletes the profile', () => {
        openSettingsMenu();
        cy.menuItem('Display profile').click();
        cy.dialog().contains(profileName).closest('li, div').find('button').last().click();
        cy.menuItem('Delete').click();
        cy.dialog('last').contains('button', /Confirm|Delete/).click();
        cy.dialog().contains(profileName).should('not.exist');
    });
});
