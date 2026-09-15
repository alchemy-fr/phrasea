/**
 * Feature 7 — Files, analysis and quarantine.
 */
import {apiRequest, deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset, waitForIndexed} from './lib/api';
import {dialogTab, login, routeDialog, visitWorkspace, waitForResults} from './lib/app';
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
        cy.visit(`${databoxNextUrl}/assets/${image.id}/manage/renditions`);
        routeDialog().within(() => {
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
        cy.getBySel('tree-quarantine').scrollIntoView().click();
        cy.url().should('include', 'assetStatus');
    });

    it('shows the analysis state of the file', () => {
        cy.visit(`${databoxNextUrl}/files/${image.source.id}/manage/info`);
        routeDialog().within(() => {
            cy.contains('Analysis').should('be.visible');
        });
    });
});
