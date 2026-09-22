/**
 * Feature 7 — Files, analysis and quarantine.
 */
import {apiRequest, deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset, waitForIndexed} from './lib/api';
import {dialogTab, login, routeDialog, visitAssetView, visitWorkspace, waitForResults} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Files & quarantine', () => {
    let ctx;
    let image;

    before(() => {
        login();
        seedWorkspace({assets: 1})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(ctx.workspace.id, 'e2e-image.png', {name: 'E2E File'});
            })
            .then(a => {
                image = a;

                return waitForAsset(image.id, asset => !!asset.source, 'source file');
            })
            .then(() => waitForIndexed({'workspaces[]': ctx.workspace.id}, 2));
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

    it('lists the renditions of the uploaded file', () => {
        visitAssetView(image.id, 'renditions').within(() => {
            cy.contains('Create custom rendition').should('be.visible');
            cy.contains('Main').should('exist');
            cy.contains('Preview').should('exist');
            cy.contains('Thumbnail').should('exist');
        });
    });

    it('opens the file dialog with metadata and references', () => {
        cy.visit(`${databoxNextUrl}/files/${image.source.id}/manage/info`);
        routeDialog().within(() => {
            cy.contains('File').should('be.visible');
            cy.contains('MIME type').should('be.visible');
            cy.contains('image/png').should('be.visible');
            cy.contains('Size').should('be.visible');
        });
        dialogTab('Metadata');
        cy.url().should('include', '/manage/metadata');
    });

    it('shows the quarantine entry in the navigation tree when analysis is required', () => {
        apiRequest({
            method: 'PUT',
            path: `/workspaces/${ctx.workspace.id}`,
            body: {name: ctx.workspace.name, fileAnalysisRequired: true},
        });
        visitWorkspace(ctx.workspace.id);
        waitForResults();
        cy.getBySel('left-panel').find('[role=tab][aria-label="Navigation"]').click();
        cy.getBySel('tree-quarantine-filter').scrollIntoView().click();
        cy.url().should('include', 'assetStatus');
    });

    it('opens the dedicated quarantine screen from the navigation tree', () => {
        visitWorkspace(ctx.workspace.id);
        waitForResults();
        cy.getBySel('left-panel').find('[role=tab][aria-label="Navigation"]').click();
        cy.getBySel('tree-quarantine').scrollIntoView().click();
        cy.url().should('include', '/quarantine');
        cy.getBySel('quarantine-queue').should('exist');
        cy.getBySel('quarantine-count').should('be.visible');
        cy.contains('a', 'Open in search').should('have.attr', 'href').and('include', 'assetStatus');
    });

    it('does not flash unfiltered results when opening the search from the quarantine screen', () => {
        cy.visit(`${databoxNextUrl}/quarantine`);
        cy.getBySel('quarantine-queue').should('exist');
        // Slow the search down to make the transition observable
        cy.intercept('GET', '**/assets?*', req => {
            req.on('response', res => res.setDelay(2000));
        }).as('filteredSearch');
        cy.contains('a', 'Open in search').click();
        cy.url().should('include', 'assetStatus');
        // The results of the previous search must not show up in between
        cy.getBySel('asset-list').should('not.exist');
        cy.wait('@filteredSearch');
        cy.get('[data-testid=asset-list], [data-testid=no-results]', {timeout: 20000}).should('exist');
    });

    it('shows the analysis state of the file', () => {
        cy.visit(`${databoxNextUrl}/files/${image.source.id}/manage/info`);
        routeDialog().within(() => {
            cy.contains('Analysis').should('be.visible');
        });
    });
});
