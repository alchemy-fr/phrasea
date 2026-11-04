/**
 * Feature 5 — Attributes: display, single asset edition, batch editor,
 * entity lists.
 */
import {createAttributeDefinition, createAttributeEntity, deleteWorkspace, iri, seedWorkspace} from './lib/api';
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
        const valueCell = text => cy.getBySel('entity-grid').contains('[data-testid=cell-value]', text);
        const rowOf = text => valueCell(text).closest('[role=row]');

        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/entities`);
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Name').type('Season');
            cy.contains('button', 'Save').click();
        });
        expectToastText('List saved');
        routeDialog().within(() => {
            cy.getBySel('definition-item').contains('Season').closest('[data-testid=definition-item]').contains('button', 'Values').click();
            cy.getBySel('entity-create').click();
        });
        cy.getBySel('entity-form-dialog').within(() => {
            cy.fieldByLabel('Value').type('Summer');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Value saved');
        valueCell('Summer').should('be.visible');

        // Inline edition, saved on Enter
        valueCell('Summer').dblclick();
        cy.focused().type('{selectAll}Winter{enter}');
        valueCell('Winter').should('be.visible');
        cy.reload();
        valueCell('Winter').should('be.visible');

        // Moderation of a pending proposal, and an attribute bound to the list for the preview
        cy.url().then(url => {
            const listId = url.split('/manage/entities/')[1].split('/')[0];
            createAttributeEntity(listId, {value: 'Autumn', status: 1});
            createAttributeDefinition(ctx.workspace.id, ctx.policy.id, {
                name: 'Season',
                type: 'entity',
                entityList: iri('entity-lists', listId),
            });
        });
        cy.reload();
        cy.getBySel('entity-pending-count', {timeout: 20000}).should('have.text', '1');
        cy.getBySel('entity-filter-pending').click();
        valueCell('Winter').should('not.exist');
        rowOf('Autumn').within(() => {
            cy.getBySel('entity-approve').click();
            cy.contains('Approved').should('be.visible');
        });
        cy.getBySel('entity-pending-count').should('not.exist');

        // Preview of the assets holding a value
        cy.getBySel('entity-filter-all').click();
        rowOf('Winter').find('[data-testid=entity-preview]').click();
        cy.getBySel('entity-preview-dialog').within(() => {
            cy.contains('Assets using this value').should('be.visible');
            cy.contains('No asset uses this value').should('be.visible');
            cy.getBySel('entity-preview-open-search').should('be.visible').and('contain', 'Open in search');
            cy.contains('button', 'Close').click();
        });
        cy.getBySel('entity-preview-dialog').should('not.exist');
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
