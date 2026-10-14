/**
 * Feature 18 — Workflows.
 */
import {deleteWorkspace, seedWorkspace, uploadAssetFromFixture, waitForAsset} from './lib/api';
import {
    assetView,
    expectToastText,
    login,
    openSettingsMenu,
    routeDialog,
    visitAssetView,
    visitAssets,
} from './lib/app';

/** Opens the last run of the asset from the workflow tab of its viewer */
function openLastRun(assetId) {
    visitAssetView(assetId, 'workflow').within(() => {
        cy.contains('button', 'View', {timeout: 60000}).first().click();
    });
    cy.url().should('include', '/workflows/');

    return routeDialog();
}

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
        openLastRun(image.id).within(() => {
            cy.contains(/Stage 1|Workflow/).should('be.visible');
            cy.contains('button', 'Refresh').should('be.visible');
            // A readable title, not `asset-ingest:<workspace id>`
            cy.get('h2').first().invoke('text').should('match', /#\d+$/);
            cy.getBySel('workflow-asset').should('contain', 'E2E Workflow');
        });
    });

    it('takes the whole window and docks the job details beside the graph', () => {
        openLastRun(image.id).then($dialog => {
            const rect = $dialog[0].getBoundingClientRect();
            cy.window().then(win => {
                expect(rect.top, 'top').to.equal(0);
                expect(rect.height, 'height').to.equal(win.innerHeight);
            });
        });
        routeDialog().within(() => {
            cy.getBySel('workflow-runs').find('[data-testid=workflow-run][aria-current=page]').should('have.length', 1);

            cy.getBySel('workflow-job-node').first().click();
            cy.getBySel('workflow-job-detail').should('be.visible');
            cy.get('.workflow-graph').then($graph => {
                cy.getBySel('workflow-job-detail').then($panel => {
                    const graph = $graph[0].getBoundingClientRect();
                    const panel = $panel[0].getBoundingClientRect();
                    // Beside the graph, not over it
                    expect(graph.right, 'graph right edge').to.be.at.most(panel.left);
                });
            });

            // The panel is resized from its edge
            cy.getBySel('workflow-job-detail').invoke('outerWidth').then(width => {
                cy.get('[role=separator]').focus().type('{leftArrow}{leftArrow}');
                cy.getBySel('workflow-job-detail').invoke('outerWidth').should('equal', width + 40);
            });
        });
    });

    it('goes back to the asset when closed', () => {
        openLastRun(image.id);
        cy.get('body').type('{esc}');
        cy.url().should('include', `/assets/${image.id}/`).and('include', '#panel=workflow');
        // The asset was loaded directly: its viewer is also rendered by the
        // page, under the one the workflow closed to
        assetView();
        cy.getBySel('asset-view')
            .last()
            .within(() => {
                cy.contains('button', 'Trigger workflow again').should('be.visible');
            });
    });

    it('triggers the workflow again', () => {
        visitAssetView(image.id, 'workflow').within(() => {
            cy.contains('button', 'Trigger workflow again').click();
        });
        expectToastText('Workflow triggered');
    });

    it('runs a new workflow from the run view and selects it', () => {
        openLastRun(image.id);
        cy.url().then(before => {
            routeDialog().within(() => {
                cy.getBySel('workflow-run').its('length').then(count => {
                    cy.contains('button', 'Run a new workflow').click();
                    cy.getBySel('workflow-run', {timeout: 30000}).should('have.length', count + 1);
                });
            });
            expectToastText('Workflow triggered');
            cy.url({timeout: 30000}).should('not.equal', before).and('include', '/workflows/');
        });
        routeDialog()
            .find('[data-testid=workflow-run]')
            .first()
            .should('have.attr', 'aria-current', 'page');
    });

    it('lists every run from the settings menu', () => {
        visitAssets();
        openSettingsMenu();
        cy.menuItem('Workflows').click();
        cy.url().should('include', '/admin/workflows');
        cy.getBySel('workflows-screen').within(() => {
            cy.getBySel('workflows-row', {timeout: 30000}).should('have.length.at.least', 1);
            cy.getBySel('workflows-row').first().find('a').first().click();
        });
        routeDialog().should('be.visible');
        cy.get('body').type('{esc}');
        cy.url().should('match', /\/admin\/workflows$/);
        cy.getBySel('workflows-screen').should('be.visible');
    });
});
