/**
 * Feature 5 — Attributes: display, single asset edition, batch editor,
 * entity lists.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {assetItem, dialogTab, expectToastText, login, openAssetContextMenu, routeDialog, visitWorkspace, waitForResults} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Attributes', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 3}).then(c => {
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

    it('displays attribute values in the viewer', () => {
        cy.visit(`${databoxNextUrl}/assets/${ctx.assets[0].id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('Description').should('be.visible');
            cy.contains('Alpha description').should('be.visible');
            cy.contains('Keywords').should('be.visible');
            cy.contains('odd').should('be.visible');
        });
    });

    it('edits the attributes of a single asset', () => {
        cy.visit(`${databoxNextUrl}/assets/${ctx.assets[1].id}/manage/edit`);
        routeDialog().within(() => {
            cy.get(`#attr-${ctx.description.id}`, {timeout: 20000}).type('{selectAll}{backspace}').should('have.value', '').type('Edited description');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Asset saved');

        cy.visit(`${databoxNextUrl}/assets/${ctx.assets[1].id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).contains('Edited description').should('be.visible');
    });

    it('edits several assets at once with the batch editor', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(3);
        assetItem('Alpha').click();
        assetItem('Charlie').click({ctrlKey: true});
        cy.getBySel('selection-actions').contains('button', 'Edit attributes').click();
        cy.url({timeout: 20000}).should('include', '/attributes/editor');
        cy.getBySel('batch-editor', {timeout: 30000}).within(() => {
            cy.contains('Edit attributes of 2 assets').should('be.visible');
            cy.contains('button', 'Keywords').click();
            cy.contains('button', 'Add to all').should('exist');
            cy.get('[data-batch-values] input, input[placeholder]').filter(':visible').first().type('batch{enter}');
            cy.contains('button', 'Save').click();
        });
        cy.dialog().within(() => {
            cy.contains('Confirm changes?').should('be.visible');
            cy.contains('button', /Confirm|Save/).click();
        });
        expectToastText('Attributes updated');

        cy.visit(`${databoxNextUrl}/assets/${ctx.assets[2].id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).contains('batch').should('be.visible');
    });

    it('manages the entity lists of the workspace', () => {
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/entities`);
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('Season');
            cy.contains('button', 'Save').click();
        });
        expectToastText('List saved');
        routeDialog().within(() => {
            cy.getBySel('definition-item').contains('Season').closest('[data-testid=definition-item]').contains('button', 'Values').click();
            cy.contains('button', 'New value').click();
            cy.fieldByLabel('Value').type('Summer');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Value saved');
        routeDialog().contains('Summer').should('be.visible');
    });

    it('shows the info tab of the asset manage dialog', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(3);
        openAssetContextMenu('Alpha');
        cy.menuItem('Info').click();
        routeDialog().within(() => {
            cy.contains('Manage asset').should('be.visible');
            cy.contains('Workspace').should('be.visible');
            cy.contains(ctx.workspace.name).should('be.visible');
        });
        dialogTab('Edit');
        cy.url().should('include', '/manage/edit');
    });
});
