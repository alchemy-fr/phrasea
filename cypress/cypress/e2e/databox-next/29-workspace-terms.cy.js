/**
 * Feature 29 — Workspace Terms & Conditions: acceptance on arrival (terms
 * gate) and at export time.
 */
import {
    apiRequest,
    createAsset,
    createWorkspace,
    deleteWorkspace,
    ensureUser,
    getUserId,
    iri,
    uniqueName,
    waitForIndexed,
} from './lib/api';
import {login, visitWorkspace, waitForResults} from './lib/app';

const termsDialog = () => cy.getBySel('workspace-terms-dialog', {timeout: 30000});

describe('Workspace terms', () => {
    let ws;

    const setTerms = terms =>
        apiRequest({method: 'PUT', path: `/workspaces/${ws.id}`, body: {terms}});
    const getWorkspace = () => apiRequest({path: `/workspaces/${ws.id}`});

    before(() => {
        login();
        ensureUser('alice');
        // Owners never sign their own terms: the workspace belongs to someone else
        getUserId('alice')
            .then(ownerId => createWorkspace({name: uniqueName('E2E Terms'), ownerId}))
            .then(w => {
                ws = w;

                return apiRequest({
                    method: 'POST',
                    path: '/rendition-policies',
                    body: {workspace: iri('workspaces', ws.id), name: 'Public', public: true, editable: true},
                });
            })
            // A rendition to pick in the export dialog
            .then(policy =>
                apiRequest({
                    method: 'POST',
                    path: '/rendition-definitions',
                    body: {workspace: iri('workspaces', ws.id), policy: iri('rendition-policies', policy.id), name: 'Main', key: 'main', buildMode: 1, useAsMain: true, download: true},
                })
            )
            .then(() => setTerms('First version of the terms'))
            // Only assets with a file can be exported: a remote one, not imported
            .then(() =>
                createAsset(ws.id, {
                    name: 'E2E Terms',
                    sourceFile: {url: 'https://example.com/e2e-terms.png', type: 'image/png', originalName: 'e2e-terms.png'},
                })
            )
            .then(() => waitForIndexed({'workspaces[]': ws.id}, 1));
    });

    after(() => {
        // A leftover workspace with unsigned terms would open the gate in every spec
        if (ws) {
            deleteWorkspace(ws.id);
        }
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
    });

    it('asks to accept the terms on arrival', () => {
        visitWorkspace(ws.id);
        termsDialog().within(() => {
            cy.contains(ws.name).should('be.visible');
            cy.contains('First version of the terms').should('be.visible');
            cy.contains('button', 'Not now').click();
        });
        cy.getBySel('workspace-terms-dialog').should('not.exist');

        // Dismissed for this visit only
        cy.reload();
        termsDialog().within(() => {
            cy.contains('version 1').should('be.visible');
            cy.contains('button', 'Accept').should('be.disabled');
            cy.get('[role=checkbox]').click();
            cy.contains('button', 'Accept').should('be.enabled').click();
        });
        cy.getBySel('workspace-terms-dialog').should('not.exist');
        getWorkspace().then(w => {
            expect(w.termsUnsigned).to.eq(false);
            expect(w.terms.signed).to.eq(true);
        });

        cy.reload();
        waitForResults(1);
        cy.getBySel('workspace-terms-dialog').should('not.exist');
    });

    it('asks to accept a new terms version at export time', () => {
        setTerms('Second version of the terms');
        cy.intercept('POST', '**/workspaces/*/terms/sign').as('sign');
        cy.intercept('POST', '**/asset-exports').as('export');

        visitWorkspace(ws.id);
        // A new version is to sign again: postponed here, signed at export
        termsDialog().contains('button', 'Not now').click();
        waitForResults(1);
        cy.getBySel('asset-item').first().rightclick();
        cy.menuItem('Download').click();

        cy.dialog().within(() => {
            cy.getBySel('export-terms')
                .should('contain', 'Second version of the terms')
                .and('contain', 'version 2');
            cy.contains('label', 'Main').find('[role=checkbox]').click();
            cy.contains('button', 'Export').should('be.disabled');
            cy.getBySel('export-terms').find('[role=checkbox]').click();
            cy.contains('button', 'Export').should('be.enabled').click();
        });

        cy.wait('@sign').its('response.statusCode').should('be.lt', 300);
        cy.wait('@export').its('response.statusCode').should('be.lt', 300);
        getWorkspace().then(w => {
            expect(w.terms.version).to.eq(2);
            expect(w.terms.signed).to.eq(true);
            expect(w.termsUnsigned).to.eq(false);
        });
    });
});
