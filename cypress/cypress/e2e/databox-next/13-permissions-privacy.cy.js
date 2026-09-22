/**
 * Feature 13 — Permissions (ACL) and privacy.
 */
import {deleteWorkspace, ensureUser, seedWorkspace} from './lib/api';
import {expectToastText, login, routeDialog, visitAssetView} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Permissions & privacy', () => {
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

    it('grants a user permission on a collection', () => {
        cy.visit(`${databoxNextUrl}/collections/${ctx.sport.id}/manage/permissions`);
        routeDialog().within(() => {
            cy.contains('No permission granted yet').should('exist');
            cy.contains('button', 'User').click();
            cy.get('[role=combobox]').first().click();
        });
        cy.get('[cmdk-input], input[placeholder*="Select a user"]').type('alice');
        cy.get('[cmdk-item], [role=option]').contains('alice', {timeout: 20000}).click();
        routeDialog().within(() => {
            cy.contains('tr', 'alice', {timeout: 20000}).as('row');
            cy.get('@row').find('[role=checkbox][aria-checked=true]').should('exist');
            // Grant one more permission (second permission column)
            cy.get('@row').find('[role=checkbox]').eq(2).click();
        });
        cy.reload();
        routeDialog().within(() => {
            cy.contains('tr', 'alice', {timeout: 20000}).find('[role=checkbox]').eq(2).should('have.attr', 'aria-checked', 'true');
        });
    });

    it('shows inherited permissions on an asset', () => {
        visitAssetView(ctx.assets[0].id, 'permissions').within(() => {
            cy.contains('Inherited permissions').should('be.visible');
        });
    });

    it('changes the privacy of a collection', () => {
        cy.visit(`${databoxNextUrl}/collections/${ctx.entertainment.id}/manage/edit`);
        routeDialog().within(() => {
            cy.fieldByLabel('Privacy').selectOption('Public');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Collection saved');
        cy.reload();
        routeDialog().within(() => {
            cy.fieldByLabel('Privacy').should('contain', 'Public');
        });
    });

    it('marks a workspace as public', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/edit`);
        routeDialog().within(() => {
            cy.fieldByLabel('Public').click();
            cy.contains('button', 'Save').click();
        });
        expectToastText('Workspace saved');
    });
});
