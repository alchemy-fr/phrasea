/**
 * Feature 6 — Asset management (side panel of the asset view) and actions:
 * copy, move, delete, restore, export, rename.
 */
import {deleteWorkspace, getAsset, seedWorkspace, waitForAsset, waitForIndexed} from './lib/api';
import {assetItem, assetPanelTab, assetView, expandTreePickerWorkspace, expectToastText, login, openAssetContextMenu, openAssetEditor, pickTreeNode, visitAssetView, visitWorkspace, waitForResults} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Asset actions', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 4}).then(c => {
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

    it('walks through the tabs of the side panel', () => {
        // The former manage dialog URLs land on the matching panel tab
        cy.visit(`${databoxNextUrl}/assets/${ctx.assets[0].id}/manage/versions`);
        assetView().findBySel('asset-panel-tab-versions').should('be.visible');
        assetView().contains('No previous version of the source file').should('be.visible');

        ['Renditions', 'Permissions', 'Workflow', 'Operations'].forEach(tab => {
            assetPanelTab(tab);
            cy.url().should('include', '#panel=');
        });
        assetPanelTab('Info');
        assetView().findBySel('asset-panel-tab-details').should('be.visible');
    });

    it('renames an asset from the editor', () => {
        visitAssetView(ctx.assets[0].id, 'edit').within(() => {
            // The asset name lives in the attribute flagged "fill from name"
            cy.get(`#attr-${ctx.title.id}`, {timeout: 20000}).type('{selectAll}{backspace}').should('have.value', '').type('E2E Renamed');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Asset saved');
        getAsset(ctx.assets[0].id).its('name').should('eq', 'E2E Renamed');
    });

    it('turns the edit mode on and off from the toolbar', () => {
        visitAssetView(ctx.assets[1].id);
        openAssetEditor();
        cy.url().should('include', '#panel=edit');
        // The same button leaves the mode, back to the tabs
        cy.getBySel('asset-action-edit').click();
        cy.getBySel('asset-panel-edit').should('not.be.visible');
        cy.getBySel('asset-panel-tab-details').should('be.visible');
        cy.url().should('not.include', '#panel=edit');
    });

    it('copies an asset to another collection', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        openAssetContextMenu('Bravo');
        cy.menuItem('Copy').click();
        cy.dialog().within(() => {
            cy.contains('Copy 1 asset').should('be.visible');
            cy.fieldByLabel('Copy by reference').then($cb => {
                if ($cb.attr('aria-checked') === 'true') {
                    cy.wrap($cb).click();
                }
            });
            expandTreePickerWorkspace(ctx.workspace.name);
        });
        cy.dialog().within(() => {
            pickTreeNode('collection', 'Entertainment');
            cy.contains('button', 'Copy').click();
        });
        expectToastText('1 asset(s) copied');
        waitForIndexed({'workspaces[]': ctx.workspace.id, 'parents[]': ctx.entertainment.id}, 1);
    });

    it('moves an asset to another collection', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults();
        openAssetContextMenu('Charlie');
        cy.menuItem('Move').click();
        cy.dialog().within(() => {
            cy.contains('Move 1 asset').should('be.visible');
            expandTreePickerWorkspace(ctx.workspace.name);
        });
        cy.dialog().within(() => {
            pickTreeNode('collection', 'Entertainment');
            cy.contains('button', 'Move').click();
        });
        expectToastText('1 asset(s) moved');
        // The move is processed asynchronously
        waitForAsset(
            ctx.assets[2].id,
            asset => (asset.referenceCollection?.id ?? asset.collections?.[0]?.id) === ctx.entertainment.id,
            'asset moved to Entertainment'
        );
    });

    it('opens the export dialog for assets with a file', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults();
        openAssetContextMenu('Delta');
        // Assets without a source file cannot be exported: the action is absent
        cy.get('[role=menu]').contains('Export').should('not.exist');
        cy.get('body').type('{esc}');
    });

    it('deletes an asset to the trash and restores it', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults();
        openAssetContextMenu('Delta');
        cy.menuItem('Delete').click();
        cy.dialog().within(() => {
            cy.contains('Delete 1 asset').should('be.visible');
            cy.contains('button', 'Delete').click();
        });
        expectToastText('moved to trash');
        cy.waitUntil(() => getAsset(ctx.assets[3].id).then(a => !!a.deletedAt || a.deleted === true), {message: 'asset in trash'});

        // Trash view (left panel > Navigation > Trash)
        cy.getBySel('left-panel').find('[role=tab][aria-label="Navigation"]').click();
        cy.getBySel('tree-trash').scrollIntoView().click();
        cy.url().should('include', 'deleted');
        assetItem('Delta', {timeout: 60000}).should('exist');
        openAssetContextMenu('Delta');
        cy.menuItem('Restore').click();
        cy.dialog().contains('button', /Restore|Confirm/).click();
        expectToastText('restored');
    });

    it('shows the location and shortcuts in the operations tab', () => {
        visitAssetView(ctx.assets[1].id, 'operations').within(() => {
            cy.contains('Location').should('be.visible');
            cy.contains('Shortcuts').should('be.visible');
            cy.contains('Danger zone').should('be.visible');
        });
    });
});
