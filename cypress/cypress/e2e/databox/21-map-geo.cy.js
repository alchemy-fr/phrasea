/**
 * Feature 21 — Geolocation: "search around me" and geo facet.
 */
import {deleteWorkspace, seedWorkspace} from './lib/api';
import {assetsUrl, login, waitForResults} from './lib/app';

describe('Geolocation', () => {
    let ctx;

    before(() => {
        login();
        seedWorkspace({assets: 1}).then(c => {
            ctx = c;
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

    it('enables and disables the "around me" filter with a stubbed position', () => {
        cy.visit(`${assetsUrl()}?f=${encodeURIComponent(`@workspace:@workspace = "${ctx.workspace.id}"`)}`, {
            onBeforeLoad(win) {
                cy.stub(win.navigator.geolocation, 'getCurrentPosition').callsFake(success =>
                    success({coords: {latitude: 48.8566, longitude: 2.3522}})
                );
            },
        });
        waitForResults(1);
        cy.getBySel('search-geo').click();
        cy.url({timeout: 20000}).should('include', 'l=48.8566');
        cy.getBySel('search-geo').click();
        cy.url().should('not.include', 'l=');
    });
});
