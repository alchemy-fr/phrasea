/**
 * Feature 8 — Upload: dropzone dialog, file selection, destination, URL
 * import, pending uploads.
 */
import {deleteWorkspace, seedWorkspace, waitForIndexed} from './lib/api';
import {assetItem, expandTreePickerWorkspace, expandTreeWorkspace, expectToastText, login, openLeftPanelTab, pickTreeNode, treeWorkspace, visitWorkspace, waitForResults} from './lib/app';

describe('Upload', () => {
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
        visitWorkspace(ctx.workspace.id);
        waitForResults();
    });

    it('uploads a file into a collection from the tree context menu', () => {
        openLeftPanelTab('Navigation');
        treeWorkspace(ctx.workspace.id).rightclick();
        cy.menuItem('Add asset').click();
        cy.dialog().within(() => {
            cy.contains('Add assets').should('be.visible');
            cy.get('input[type=file]').selectFile('cypress/fixtures/e2e-image.png', {force: true});
            cy.contains('e2e-image.png').should('be.visible');
            expandTreePickerWorkspace(ctx.workspace.name);
        });
        cy.dialog().within(() => {
            pickTreeNode('collection', 'Sport');
            cy.contains('button', /Upload 1 file/).click();
        });
        expectToastText(/uploaded|Upload complete/);
        waitForIndexed({'workspaces[]': ctx.workspace.id, 'parents[]': ctx.sport.id}, 2);
        cy.reload();
        assetItem('e2e-image', {timeout: 60000}).should('exist');
    });

    it('uploads a large file in parts with the URLs given at creation', () => {
        // Several parts with the dev minimum part size (5 MiB)
        const size = 12 * 1024 * 1024;
        cy.intercept('POST', /\/uploads$/).as('createUpload');
        cy.intercept('POST', /\/uploads\/[^/]+\/parts?$/, cy.spy().as('partUrlRequest'));
        cy.intercept('PUT', /[?&]partNumber=\d+/).as('putPart');
        cy.intercept('POST', /\/assets$/).as('createAsset');

        openLeftPanelTab('Navigation');
        treeWorkspace(ctx.workspace.id).rightclick();
        cy.menuItem('Add asset').click();
        cy.dialog().within(() => {
            cy.get('input[type=file]').selectFile(
                {
                    contents: Cypress.Buffer.alloc(size, 'x'),
                    fileName: 'e2e-multipart.bin',
                    mimeType: 'application/octet-stream',
                },
                {force: true}
            );
            cy.contains('e2e-multipart.bin').should('be.visible');
            expandTreePickerWorkspace(ctx.workspace.name);
        });
        cy.dialog().within(() => {
            pickTreeNode('collection', 'Sport');
            cy.contains('button', /Upload 1 file/).click();
        });

        cy.wait('@createUpload').then(({response}) => {
            expect(response.statusCode).to.eq(201);
            const {chunkSize, urls} = response.body;
            const partCount = Math.ceil(size / chunkSize);
            expect(partCount, 'part count').to.be.greaterThan(1);
            expect(Object.keys(urls), 'presigned URLs').to.have.length(partCount);
            cy.wrap(partCount).as('partCount');
        });
        cy.wait('@createAsset', {timeout: 60000}).then(({request, response}) => {
            expect(response.statusCode).to.eq(201);
            cy.get('@partCount').then(partCount => {
                expect(request.body.multipart.parts.map(p => p.PartNumber)).to.deep.eq(
                    Array.from({length: partCount}, (_, i) => i + 1)
                );
                cy.get('@putPart.all').should('have.length', partCount);
            });
        });
        expectToastText(/uploaded|Upload complete/);
        // Every part URL came with the creation response
        cy.get('@partUrlRequest').should('not.have.been.called');
    });

    it('imports assets from URLs', () => {
        openLeftPanelTab('Navigation');
        expandTreeWorkspace(ctx.workspace.id);
        treeWorkspace(ctx.workspace.id).rightclick();
        cy.menuItem('Add asset').click();
        cy.dialog().within(() => {
            cy.get('[role=tab]').contains('URLs').click();
            cy.get('textarea').first().type('https://example.com/not-a-real-image.png{enter}not a url');
            cy.contains('1 invalid URL').should('be.visible');
        });
        cy.get('body').type('{esc}');
    });

    it('drops files on the results screen', () => {
        cy.getBySel('asset-list').selectFile('cypress/fixtures/e2e-image.png', {action: 'drag-drop', force: true});
        cy.get('[role=dialog]', {timeout: 20000}).within(() => {
            cy.contains('Add assets').should('be.visible');
            cy.contains('e2e-image.png').should('be.visible');
            cy.contains('button', 'Cancel').click();
        });
        cy.get('[role=dialog]').then($d => {
            if ($d.length && $d.text().includes('Discard pending files?')) {
                cy.wrap($d).contains('button', /Discard|Confirm/).click();
            }
        });
    });
});
