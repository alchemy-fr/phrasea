/**
 * Feature 8 — Upload: dropzone dialog, file selection, destination, URL
 * import, pending uploads.
 */
import {deleteWorkspace, seedWorkspace, waitForIndexed} from './lib/api';
import {assetItem, expandTreePickerWorkspace, expandTreeWorkspace, expectToastText, login, openLeftPanelTab, pickTreeNode, treeWorkspace, visitWorkspace, waitForResults} from './lib/app';

describe('Upload', () => {
    let ctx;

    before(() => {
        login();
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
        visitWorkspace(ctx.workspace.id);
        waitForResults();
    });

    it('uploads a file into a collection from the tree context menu', () => {
        openLeftPanelTab('Navigation');
        treeWorkspace(ctx.workspace.id).rightclick();
        cy.menuItem('Add asset').click();
        cy.dialog().within(() => {
            cy.contains('Add assets').should('be.visible');
            cy.get('input[type=file]').selectFile('cypress/fixtures/e2e-image.png', {force: true});
            cy.contains('e2e-image.png').should('be.visible');
            expandTreePickerWorkspace(ctx.workspace.name);
        });
        cy.dialog().within(() => {
            pickTreeNode('collection', 'Sport');
            cy.contains('button', /Upload 1 file/).click();
        });
        expectToastText(/uploaded|Upload complete/);
        waitForIndexed({'workspaces[]': ctx.workspace.id, 'parents[]': ctx.sport.id}, 2);
        cy.reload();
        assetItem('e2e-image', {timeout: 60000}).should('exist');
    });

    it('imports assets from URLs', () => {
        openLeftPanelTab('Navigation');
        expandTreeWorkspace(ctx.workspace.id);
        treeWorkspace(ctx.workspace.id).rightclick();
        cy.menuItem('Add asset').click();
        cy.dialog().within(() => {
            cy.get('[role=tab]').contains('URLs').click();
            cy.get('textarea').first().type('https://example.com/not-a-real-image.png{enter}not a url');
            cy.contains('1 invalid URL').should('be.visible');
        });
        cy.get('body').type('{esc}');
    });

    it('drops files on the results screen', () => {
        cy.getBySel('asset-list').selectFile('cypress/fixtures/e2e-image.png', {action: 'drag-drop', force: true});
        cy.get('[role=dialog]', {timeout: 20000}).within(() => {
            cy.contains('Add assets').should('be.visible');
            cy.contains('e2e-image.png').should('be.visible');
            cy.contains('button', 'Cancel').click();
        });
        cy.get('[role=dialog]').then($d => {
            if ($d.length && $d.text().includes('Discard pending files?')) {
                cy.wrap($d).contains('button', /Discard|Confirm/).click();
            }
        });
    });
});
