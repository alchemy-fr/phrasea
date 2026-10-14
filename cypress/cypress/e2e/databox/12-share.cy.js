/**
 * Feature 12 — Share: public links, share page, embed code, revocation, links
 * sharing several assets at once, the URL of each shared rendition, and the
 * management of a link from one of its assets.
 */
import {
    deleteWorkspace,
    seedWorkspace,
    uploadAssetFromFixture,
    waitForAsset,
    waitForIndexed,
} from './lib/api';
import {
    assetItem,
    expectToast,
    login,
    openAssetContextMenu,
    visitWorkspace,
    waitForResults,
} from './lib/app';

/** Radix menu items are portaled to <body>: escape any `.within()` scope */
const menuItems = () =>
    cy.get('[role=menuitem]', {withinSubject: null, timeout: 10000});

describe('Share', () => {
    let ctx;
    let image;
    let shareUrl;
    let multiUrl;

    before(() => {
        login();
        seedWorkspace({assets: 1})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(
                    ctx.workspace.id,
                    'e2e-image.png',
                    {name: 'E2E Shared'}
                );
            })
            .then(a => {
                image = a;

                return waitForAsset(
                    image.id,
                    asset => !!asset.source && !!asset.thumbnail,
                    'renditions'
                );
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

            // The link to copy: the asset page, or one of the shared renditions
            cy.getBySel('share-link-choice')
                .should('contain', 'Asset page')
                .click();
        });
        menuItems().should('contain', 'Preview').and('contain', 'Thumbnail');
        menuItems().contains('Thumbnail').click();
        cy.dialog().within(() => {
            cy.getBySel('share-link').should('contain', 'Thumbnail');
            cy.getBySel('share-link')
                .find('a')
                .invoke('attr', 'href')
                .should('match', /\/s\/[^/]+\/a\/[^/]+\/r\/[^/?]+\?.*token=/);
            cy.getBySel('share-link-choice').click();
        });
        menuItems().contains('Asset page').click();
        cy.dialog().within(() => {
            cy.getBySel('share-link').should('contain', 'Asset page');
            cy.contains('button', 'Embed code').click();
        });
        cy.dialog('last').within(() => {
            cy.contains('Embed code').should('be.visible');
            cy.get('textarea')
                .invoke('val')
                .should('match', /<iframe|<img/);
        });
        cy.get('body').type('{esc}');
    });

    it('opens the shared page anonymously', () => {
        Cypress.session.clearCurrentSessionData();
        cy.visit(shareUrl);
        cy.getBySel('share-asset-name', {timeout: 30000}).should(
            'contain',
            'E2E Shared'
        );
        cy.get('[data-testid=share-asset-view] img').should('be.visible');
        // The header of the application, for an anonymous visitor
        cy.getBySel('topbar').within(() => {
            cy.contains('Databox').should('be.visible');
            cy.getBySel('settings-menu').should('be.visible');
            cy.getBySel('sign-in').should('be.visible');
        });

        // One line per rendition, identified by the rendition
        cy.getBySel('share-download').click();
        cy.dialog().within(() => {
            cy.getBySel('share-download-rendition').should(
                'have.length.at.least',
                2
            );
            cy.contains('button', /^Download$/).should('be.disabled');
            cy.getBySel('share-download-rendition').first().click();
            cy.contains('button', /^Download$/).should('not.be.disabled');
        });
        cy.get('body').type('{esc}');
    });

    it('revokes the link', () => {
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        openAssetContextMenu('E2E Shared');
        cy.menuItem('Share').click();
        cy.dialog().within(() => {
            cy.fieldByLabel('Create a public link')
                .should('have.attr', 'aria-checked', 'true')
                .click();
            cy.fieldByLabel('Create a public link').should(
                'have.attr',
                'aria-checked',
                'false'
            );
        });
        Cypress.session.clearCurrentSessionData();
        cy.visit(shareUrl, {failOnStatusCode: false});
        cy.contains('This link is not valid or has expired', {
            timeout: 30000,
        }).should('be.visible');
    });

    it('shares several assets under a single link', () => {
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        assetItem('E2E Shared').click();
        assetItem('Alpha').click({ctrlKey: true});
        cy.get('[data-testid=asset-item][data-selected=true]').should(
            'have.length',
            2
        );
        cy.getBySel('selection-actions').contains('button', 'Share').click();
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

            // The shared assets, with the URL of each rendition the link delivers
            cy.getBySel('share-asset-row').should('have.length', 2);
            // Alpha has no file, so no rendition to copy
            cy.getBySel('share-asset-row')
                .contains('Alpha')
                .closest('[data-testid=share-asset-row]')
                .findBySel('share-asset-copy')
                .should('be.disabled');
            cy.getBySel('share-asset-row')
                .contains('E2E Shared')
                .closest('[data-testid=share-asset-row]')
                .findBySel('share-asset-copy')
                .click();
        });
        menuItems().should('contain', 'Thumbnail');
        menuItems().contains('Thumbnail').click();
        expectToast('Link copied');
        cy.get('body').type('{esc}');

        cy.then(() => {
            Cypress.session.clearCurrentSessionData();
            cy.visit(multiUrl);
        });
        cy.getBySel('share-asset', {timeout: 30000}).should('have.length', 2);
        cy.contains('E2E Shared').should('be.visible');

        // Grid, masonry and list layouts
        cy.getBySel('share-gallery').should('have.attr', 'data-layout', 'grid');
        cy.getBySel('share-layout').find('[role=tab]').eq(1).click();
        cy.getBySel('share-gallery').should(
            'have.attr',
            'data-layout',
            'masonry'
        );
        cy.getBySel('share-layout').find('[role=tab]').eq(2).click();
        cy.getBySel('share-gallery').should('have.attr', 'data-layout', 'list');
        cy.getBySel('share-asset').should('have.length', 2);
        cy.getBySel('share-layout').find('[role=tab]').eq(0).click();

        // The viewer: linkable, walks the assets, closes back to the gallery.
        // The order of the shared assets is not defined: start from the first one.
        cy.getBySel('share-asset-title')
            .first()
            .invoke('text')
            .then(text => {
                const firstTitle = text.trim();
                cy.getBySel('share-asset-title').first().click();
                cy.getBySel('share-asset-name').should('contain', firstTitle);
                cy.location('search').should('contain', 'asset=');
                cy.getBySel('share-view-prev').should('be.disabled');
                cy.getBySel('share-view-next')
                    .should('not.be.disabled')
                    .click();
                cy.getBySel('share-asset-name').should(
                    'not.contain',
                    firstTitle
                );
                cy.getBySel('share-view-prev')
                    .should('not.be.disabled')
                    .click();
                cy.getBySel('share-asset-name').should('contain', firstTitle);
            });
        cy.getBySel('share-view-close').click();
        cy.getBySel('share-gallery').should('be.visible');
        cy.location('search').should('not.contain', 'asset=');

        // Downloading several assets: a line per rendition name
        cy.getBySel('share-download-all').click();
        cy.dialog().within(() => {
            cy.contains('Download 2 assets').should('be.visible');
            cy.getBySel('share-download-rendition').should(
                'have.length.at.least',
                1
            );
        });
        cy.get('body').type('{esc}');
    });

    it('manages a link of several assets from one of them', () => {
        login();
        visitWorkspace(ctx.workspace.id);
        waitForResults(2);
        openAssetContextMenu('E2E Shared');
        cy.menuItem('Share').click();

        // The link of both assets is listed for one of them, with its asset count
        cy.dialog().within(() => {
            cy.getBySel('share-item').should('have.length', 1);
            cy.getBySel('share-assets-count').should('have.text', '2 assets');

            // Name and dates are editable in place
            cy.get('button[aria-label="Edit"]').click();
            cy.fieldByLabel('Name / audience').type('Press');
            cy.fieldByLabel('Expires at').type('2099-12-31T10:00');
            cy.contains('button', 'Save').click();
            cy.getBySel('share-item')
                .should('contain', 'Press')
                .and('contain', 'until');
            cy.contains('label', 'Name / audience').should('not.exist');

            // One asset is removed from the link
            cy.getBySel('share-asset-row').should('have.length', 2);
            cy.getBySel('share-asset-row')
                .contains('Alpha')
                .closest('[data-testid=share-asset-row]')
                .findBySel('share-asset-remove')
                .click();
            cy.getBySel('share-assets-count').should('have.text', '1 asset');
            cy.getBySel('share-asset-row').should('not.exist');
        });

        // The removal is undone from its toast (shown for 5 s), then done again.
        // Sonner re-renders the toast while it settles: a Cypress click retries
        // on the detached button, so the click is dispatched natively at once.
        cy.contains('[data-sonner-toast] button', 'Undo', {
            timeout: 10000,
        }).then($undo => {
            $undo[0].click();
        });
        cy.dialog().within(() => {
            cy.getBySel('share-assets-count').should('have.text', '2 assets');
            cy.getBySel('share-asset-row').should('have.length', 2);
            cy.getBySel('share-asset-row')
                .contains('Alpha')
                .closest('[data-testid=share-asset-row]')
                .findBySel('share-asset-remove')
                .click();
            cy.getBySel('share-assets-count').should('have.text', '1 asset');
            cy.getBySel('share-asset-row').should('not.exist');
        });
        cy.get('body').type('{esc}');

        // The public page only delivers the remaining asset, under the new name
        cy.then(() => {
            Cypress.session.clearCurrentSessionData();
            cy.visit(multiUrl);
        });
        cy.getBySel('share-asset-name', {timeout: 30000}).should(
            'contain',
            'E2E Shared'
        );
        cy.getBySel('share-asset').should('not.exist');
    });
});
