/**
 * Feature 5 — Attributes: display, single asset edition, batch editor,
 * entity lists.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {assetItem, assetView, expectToastText, login, openAssetContextMenu, openAssetEditor, routeDialog, visitAssetView, visitWorkspace, waitForResults} from './lib/app';
import {databoxUrl} from '../lib/urls';

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
        cy.visit(`${databoxUrl}/assets/${ctx.assets[0].id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('Description').should('be.visible');
            cy.contains('Alpha description').should('be.visible');
            cy.contains('Keywords').should('be.visible');
            cy.contains('odd').should('be.visible');
        });
    });

    it('edits the attributes of a single asset', () => {
        visitAssetView(ctx.assets[1].id, 'edit').within(() => {
            cy.get(`#attr-${ctx.description.id}`, {timeout: 20000}).type('{selectAll}{backspace}').should('have.value', '').type('Edited description');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Asset saved');

        cy.visit(`${databoxUrl}/assets/${ctx.assets[1].id}/_`);
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

        cy.visit(`${databoxUrl}/assets/${ctx.assets[2].id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).contains('batch').should('be.visible');
    });

    it('sub-selects assets in the batch editor', () => {
        cy.intercept('GET', '**/attributes?*').as('assetAttributes');
        visitWorkspace(ctx.workspace.id);
        waitForResults(3);
        assetItem('Alpha').click();
        assetItem('Charlie').click({shiftKey: true});
        cy.getBySel('selection-actions').contains('button', 'Edit attributes').click();
        cy.getBySel('batch-editor', {timeout: 30000}).within(() => {
            cy.contains('Edit attributes of 3 assets').should('be.visible');
            cy.contains('3 / 3 selected').should('be.visible');
            cy.contains('button', 'Select all').should('be.disabled');

            cy.getBySel('batch-thumb').eq(0).find('button').click();
            cy.contains('1 / 3 selected').should('be.visible');
            cy.getBySel('batch-thumb').eq(2).find('button').click({shiftKey: true});
            cy.contains('3 / 3 selected').should('be.visible');
            cy.getBySel('batch-thumb').eq(1).find('button').click();
            cy.contains('1 / 3 selected').should('be.visible');
        });
        // The asset list behind the editor must not catch the shortcut
        cy.get('body').type('{ctrl}a');
        cy.getBySel('batch-editor').contains('3 / 3 selected').should('be.visible');
        // Attributes come with the assets
        cy.get('@assetAttributes.all').should('have.length', 0);
    });

    it('manages the entity lists of the workspace', () => {
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/entities`);
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

    it('shows the info of an asset in the side panel', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(3);
        openAssetContextMenu('Alpha');
        cy.menuItem('Info').click();
        assetView().within(() => {
            cy.contains('button', 'Information').click();
            cy.contains('Workspace').should('be.visible');
            cy.contains(ctx.workspace.name).should('be.visible');
        });
        // Editing is a mode of the panel, turned on from the toolbar
        openAssetEditor();
        cy.url().should('include', '#panel=edit');
    });
});
