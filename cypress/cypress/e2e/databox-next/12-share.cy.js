/**
 * Feature 12 — Share: public links, share page, embed code, revocation, and
 * links sharing several assets at once.
 */
import {deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset, waitForIndexed} from './lib/api';
import {assetItem, login, openAssetContextMenu, visitWorkspace, waitForResults} from './lib/app';

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

    it('shares several assets under a single link', () => {
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        assetItem('E2E Shared').click();
        assetItem('Alpha').click({ctrlKey: true});
        cy.get('[data-testid=asset-item][data-selected=true]').should('have.length', 2);
        cy.getBySel('selection-actions').contains('button', 'Share').click();
        let multiUrl;
        cy.dialog().within(() => {
            cy.contains('Share 2 assets').should('be.visible');
            cy.fieldByLabel('Create a public link').click();
            cy.get('a[href*="/s/"]', {timeout: 20000})
                .first()
                .invoke('attr', 'href')
                .then(href => {
                    expect(href).not.to.equal(shareUrl);
                    multiUrl = href;
                });
        });
        cy.get('body').type('{esc}');

        cy.then(() => {
            Cypress.session.clearCurrentSessionData();
            cy.visit(multiUrl);
        });
        cy.getBySel('share-asset', {timeout: 30000}).should('have.length', 2);
        cy.contains('E2E Shared').should('be.visible');
    });
});
