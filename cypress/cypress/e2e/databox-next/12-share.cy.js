/**
 * Feature 12 — Share: public links, share page, embed code, revocation.
 */
import {deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset, waitForIndexed} from './lib/api';
import {login, openAssetContextMenu, visitWorkspace, waitForResults} from './lib/app';

describe('Share', () => {
    let ctx;
    let image;
    let shareUrl;

    before(() => {
        login();
        seedWorkspace({assets: 1})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(ctx.workspace.id, 'e2e-image.png', {name: 'E2E Shared'});
            })
            .then(a => {
                image = a;

                return waitForAsset(image.id, asset => !!asset.source && !!asset.thumbnail, 'renditions');
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
    });

    it('creates a public link from the share dialog', () => {
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        openAssetContextMenu('E2E Shared');
        cy.menuItem('Share').click();
        cy.dialog().within(() => {
            cy.contains('Share asset').should('be.visible');
            cy.fieldByLabel('Create a public link').click();
            cy.get('a[href*="/s/"]', {timeout: 20000})
                .first()
                .invoke('attr', 'href')
                .then(href => {
                    expect(href).to.match(/\/s\/[^/]+\/[^/]+/);
                    shareUrl = href;
                });
            cy.contains('button', 'Copy link').should('be.visible');
            cy.contains('button', 'Embed code').click();
        });
        cy.dialog('last').within(() => {
            cy.contains('Embed code').should('be.visible');
            cy.get('textarea').invoke('val').should('match', /<iframe|<img/);
        });
        cy.get('body').type('{esc}');
    });

    it('opens the shared page anonymously', () => {
        Cypress.session.clearCurrentSessionData();
        cy.visit(shareUrl);
        cy.contains('E2E Shared', {timeout: 30000}).should('be.visible');
        cy.get('img').should('be.visible');
    });

    it('revokes the link', () => {
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        openAssetContextMenu('E2E Shared');
        cy.menuItem('Share').click();
        cy.dialog().within(() => {
            cy.fieldByLabel('Create a public link').should('have.attr', 'aria-checked', 'true').click();
            cy.fieldByLabel('Create a public link').should('have.attr', 'aria-checked', 'false');
        });
        Cypress.session.clearCurrentSessionData();
        cy.visit(shareUrl, {failOnStatusCode: false});
        cy.contains('This link is not valid or has expired', {timeout: 30000}).should('be.visible');
    });
});
