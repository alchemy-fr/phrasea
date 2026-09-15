/**
 * Feature 18 — Workflows.
 */
import {deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset} from './lib/api';
import {expectToastText, login, routeDialog} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

describe('Workflows', () => {
    let ctx;
    let image;

    before(() => {
        login();
        seedWorkspace({assets: 1})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(ctx.workspace.id, 'e2e-image.png', {name: 'E2E Workflow'});
            })
            .then(a => {
                image = a;

                return waitForAsset(image.id, asset => !!asset.source, 'source file');
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

    it('lists the ingestion workflow of an uploaded asset and opens it', () => {
        cy.visit(`${databoxNextUrl}/assets/${image.id}/manage/workflow`);
        routeDialog().within(() => {
            cy.contains('button', 'View', {timeout: 60000}).first().click();
        });
        cy.url().should('include', '/workflows/');
        routeDialog().within(() => {
            cy.contains(/Stage 1|Workflow/).should('be.visible');
            cy.contains('button', 'Refresh').should('be.visible');
        });
    });

    it('triggers the workflow again', () => {
        cy.visit(`${databoxNextUrl}/assets/${image.id}/manage/workflow`);
        routeDialog().within(() => {
            cy.contains('button', 'Trigger workflow again').click();
        });
        expectToastText('Workflow triggered');
    });
});
