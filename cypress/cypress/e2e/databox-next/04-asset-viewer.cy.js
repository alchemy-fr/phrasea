/**
 * Feature 4 — Asset viewer: routed full-screen view, side panel sections,
 * navigation between results, keyboard shortcuts.
 */
import {deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset, waitForIndexed} from './lib/api';
import {assetItem, login, openAsset, visitWorkspace, waitForResults} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Asset viewer', () => {
    let ctx;
    let image;

    before(() => {
        login();
        seedWorkspace({assets: 3})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(ctx.workspace.id, 'e2e-image.png', {
                    name: 'E2E Image',
                    collection: `/collections/${ctx.sport.id}`,
                });
            })
            .then(a => {
                image = a;

                return waitForAsset(image.id, asset => !!asset.source && !!asset.thumbnail, 'renditions of the uploaded image');
            })
            .then(() => waitForIndexed({'workspaces[]': ctx.workspace.id}, 4));
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

    it('opens an asset in the viewer with a shareable URL', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        openAsset('E2E Image');
        cy.url().should('match', new RegExp(`/assets/${image.id}/`));
        cy.getBySel('asset-view').within(() => {
            cy.contains('E2E Image').should('be.visible');
            cy.get('img').should('be.visible');
        });
    });

    it('shows the side panel sections', () => {
        cy.visit(`${databoxNextUrl}/assets/${image.id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('button', 'Attributes').should('be.visible');
            cy.contains('button', 'Information').click();
            cy.contains('Workspace').should('be.visible');
            cy.contains(ctx.workspace.name).should('be.visible');
            cy.contains('button', 'Appears in').click();
            cy.contains('Sport').should('be.visible');
            cy.contains('button', 'Discussion').should('be.visible');
            cy.contains('button', 'Attachments').should('be.visible');
        });
    });

    it('hides and shows the side panel', () => {
        cy.visit(`${databoxNextUrl}/assets/${image.id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.get('[aria-label="Hide panel"]').click();
            cy.contains('button', 'Attributes').should('not.be.visible');
            cy.get('[aria-label="Show panel"]').click();
            cy.contains('button', 'Attributes').should('be.visible');
        });
    });

    it('navigates to the previous / next result with buttons and arrow keys', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        // Follow the order of the results list
        cy.getBySel('asset-item').then($items => {
            const names = $items.toArray().map(el => el.querySelector('[data-testid=asset-item-title]').textContent.trim());
            openAsset(names[0]);
            cy.getBySel('asset-view-title').should('have.text', names[0]);
            cy.get('[aria-label="Next"]').click();
            cy.getBySel('asset-view-title').should('have.text', names[1]);
            cy.get('body').type('{rightarrow}');
            cy.getBySel('asset-view-title').should('have.text', names[2]);
            cy.get('body').type('{leftarrow}');
            cy.getBySel('asset-view-title').should('have.text', names[1]);
            cy.get('[aria-label="Previous"]').click();
            cy.getBySel('asset-view-title').should('have.text', names[0]);
        });
    });

    it('closes the viewer with Escape and gets back to the results', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults(4);
        openAsset('Bravo');
        cy.get('body').type('{esc}');
        cy.getBySel('asset-view').should('not.exist');
        cy.url().should('include', '/assets');
        assetItem('Bravo').should('be.visible');
    });

    it('opens the viewer from a direct link when not authenticated on a private asset', () => {
        Cypress.session.clearCurrentSessionData();
        cy.visit(`${databoxNextUrl}/assets/${image.id}/_`);
        cy.contains(/Asset not found|Sign in/, {timeout: 30000}).should('be.visible');
    });
});
