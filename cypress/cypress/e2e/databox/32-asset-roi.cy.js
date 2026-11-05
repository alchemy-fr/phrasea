/**
 * Feature 32 — Region of interest of an asset: shown by default in the viewer,
 * the whole asset on demand, edited (removed) by a user who can edit the asset.
 * The time ranges (video, audio) are covered by the unit tests: no such fixture.
 */
import {
    apiRequest,
    deleteWorkspace,
    seedWorkspace,
    uploadAssetFromFixture,
    waitForAsset,
} from './lib/api';
import {assetView, login, visitAssetView} from './lib/app';

describe('Asset region of interest', () => {
    let ctx;
    let image;

    before(() => {
        login();
        seedWorkspace({assets: 0})
            .then(c => {
                ctx = c;

                return uploadAssetFromFixture(
                    ctx.workspace.id,
                    'e2e-image.png',
                    {name: 'E2E ROI'}
                );
            })
            .then(a => {
                image = a;

                return waitForAsset(
                    image.id,
                    asset => !!asset.source && !!asset.thumbnail,
                    'renditions of the uploaded image'
                );
            })
            .then(() =>
                apiRequest({
                    method: 'PATCH',
                    path: `/assets/${image.id}`,
                    body: {roi: {crop: {x: 0.25, y: 0.25, w: 0.5, h: 0.5}}},
                })
            )
            .then(asset => {
                expect(asset.roi).to.deep.equal({
                    crop: {x: 0.25, y: 0.25, w: 0.5, h: 0.5},
                });
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

    it('shows the region of interest, or the whole asset', () => {
        visitAssetView(image.id);
        assetView().within(() => {
            cy.getBySel('clip-frame').should('be.visible');

            cy.getBySel('asset-roi-toggle').click();
            cy.getBySel('clip-frame').should('not.exist');
            cy.getBySel('asset-roi-toggle').should(
                'have.attr',
                'aria-pressed',
                'true'
            );

            cy.getBySel('asset-roi-toggle').click();
            cy.getBySel('clip-frame').should('be.visible');
        });
    });

    it('removes the region of interest', () => {
        visitAssetView(image.id);
        assetView().within(() => {
            cy.getBySel('clip-frame').should('be.visible');
            cy.getBySel('asset-roi-edit').click();
        });
        cy.dialog().within(() => {
            cy.getBySel('clip-remove').click();
        });
        cy.get('[role=dialog][data-state=open]').should('not.exist');

        assetView().within(() => {
            cy.getBySel('asset-roi-toggle').should('not.exist');
            cy.getBySel('clip-frame').should('not.exist');
        });
        apiRequest({path: `/assets/${image.id}`}).then(asset => {
            expect(asset.roi).to.be.undefined;
        });
    });
});
