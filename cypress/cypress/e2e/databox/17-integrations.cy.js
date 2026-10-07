/**
 * Feature 17 — Integrations: workspace administration and asset side panel.
 */
import {apiRequest, deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset} from './lib/api';
import {expectToastText, login, routeDialog} from './lib/app';
import {databoxUrl} from '../lib/urls';

describe('Integrations', () => {
    let ctx;
    let image;

    before(() => {
        login();
        seedWorkspace({assets: 1})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(ctx.workspace.id, 'e2e-image.png', {name: 'E2E Integrations'});
            })
            .then(a => {
                image = a;

                return waitForAsset(image.id, asset => !!asset.source && !!asset.thumbnail, 'renditions');
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

    it('creates a workspace integration', () => {
        cy.visit(`${databoxUrl}/workspaces/${ctx.workspace.id}/manage/integrations`);
        // The tab renders once the integration types are loaded
        cy.getBySel('definition-create', {timeout: 30000}).should('be.visible');
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            // The type is picked in the catalog, then the form shows up
            cy.getBySel('integration-catalog-item').first().click();
            cy.fieldByLabel('Title').type('E2E integration');
            cy.contains('Configuration (YAML)').should('be.visible');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Integration saved');
        routeDialog().getBySel('definition-item').should('contain', 'E2E integration');
    });

    it('shows one section per integration of the file in the asset viewer', () => {
        apiRequest({
            method: 'POST',
            path: '/integrations',
            body: {
                workspace: `/workspaces/${ctx.workspace.id}`,
                integration: 'tui.photo-editor',
                name: 'E2E photo editor',
                enabled: true,
                public: false,
            },
        });

        cy.visit(`${databoxUrl}/assets/${image.id}/_`);
        cy.getBySel('asset-view', {timeout: 30000})
            .find('[data-testid=asset-integration]', {timeout: 30000})
            .should('contain', 'E2E photo editor')
            .and('contain', 'Toast UI Photo Editor');
    });

    it('edits the displayed file with the Toast UI photo editor', () => {
        cy.visit(`${databoxUrl}/assets/${image.id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('[data-testid=asset-integration] button', 'E2E photo editor', {timeout: 30000}).click();
            cy.contains('button', 'Open photo editor', {timeout: 30000}).click();
        });

        // The editor is a full screen dialog over the viewer
        cy.get('.tui-image-editor-container', {timeout: 60000}).should('be.visible');
        cy.dialog('last').within(() => {
            cy.get('input[placeholder="File name"]').type('E2E edited');
            cy.contains('button', 'Save as').click();
        });
        expectToastText('Saved!');

        // The export is listed in the panel, ready to be reopened
        cy.getBySel('asset-view').contains('E2E edited', {timeout: 30000}).should('be.visible');
    });
});
