/**
 * Feature 17 — Integrations: workspace administration and asset side panel.
 */
import {deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset} from './lib/api';
import {expectToastText, login, routeDialog} from './lib/app';
import {databoxNextUrl} from '../lib/urls';

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
        cy.visit(`${databoxNextUrl}/workspaces/${ctx.workspace.id}/manage/integrations`);
        // The tab renders once the integration types are loaded
        cy.getBySel('definition-create', {timeout: 30000}).should('be.visible');
        routeDialog().within(() => {
            cy.getBySel('definition-create').click();
            cy.fieldByLabel('Type').click();
        });
        cy.get('[role=option]').first().click();
        routeDialog().within(() => {
            cy.fieldByLabel('Title').type('E2E integration');
            cy.contains('Configuration (YAML)').should('be.visible');
            cy.contains('button', 'Save').click();
        });
        expectToastText('Integration saved');
        routeDialog().getBySel('definition-item').should('contain', 'E2E integration');
    });

    it('shows the integrations section of the asset viewer', () => {
        cy.visit(`${databoxNextUrl}/assets/${image.id}/_`);
        cy.getBySel('asset-view', {timeout: 30000}).within(() => {
            cy.contains('button', 'Integrations').click();
            cy.contains(/No integration available|E2E integration/).should('be.visible');
        });
    });
});
