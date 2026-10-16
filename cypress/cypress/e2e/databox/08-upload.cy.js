/**
 * Feature 8 — Upload: dropzone dialog, file selection, destination, URL
 * import, file-less assets and stories, pending uploads.
 */
import {deleteWorkspace, seedWorkspace, waitForAssetIndexed, waitForIndexed} from './lib/api';
import {assetItem, expandTreePickerWorkspace, expandTreeWorkspace, expectToastText, login, openLeftPanelTab, pickTreeNode, treeWorkspace, visitWorkspace, waitForResults} from './lib/app';

/**
 * Opens the upload dialog from the workspace context menu and waits for the
 * menu to unmount: Radix then gives the focus back to the tree node, outside
 * the dialog, whose focus trap pulls it back into the field being typed in
 * with its text selected, so the next keystroke would replace it.
 */
function openUploadFromTree(workspaceId) {
    treeWorkspace(workspaceId).rightclick();
    cy.menuItem('Add asset').click();
    cy.get('[role=menu]').should('not.exist');
}

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
        openUploadFromTree(ctx.workspace.id);
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
        // Several parts with the e2e minimum part size (S3_MULTIPART_MIN_CHUNK_SIZE: 5 MiB in the CI)
        const size = 12 * 1024 * 1024;
        cy.intercept('POST', /\/uploads$/).as('createUpload');
        cy.intercept('POST', /\/uploads\/[^/]+\/parts?$/, cy.spy().as('partUrlRequest'));
        cy.intercept('PUT', /[?&]partNumber=\d+/).as('putPart');
        cy.intercept('POST', /\/assets$/).as('createAsset');

        openLeftPanelTab('Navigation');
        openUploadFromTree(ctx.workspace.id);
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
        openUploadFromTree(ctx.workspace.id);
        cy.dialog().within(() => {
            cy.get('[role=tab]').contains('URLs').click();
            cy.get('textarea').first().type('https://example.com/not-a-real-image.png{enter}not a url');
            cy.contains('1 invalid URL').should('be.visible');
        });
        cy.get('body').type('{esc}');
    });

    it('creates an asset without any file', () => {
        cy.intercept('POST', /\/assets$/).as('createAsset');

        openLeftPanelTab('Navigation');
        openUploadFromTree(ctx.workspace.id);
        cy.dialog().within(() => {
            cy.get('[role=tab]').contains('No file').click();
            cy.getBySel('upload-asset-title').type('e2e-no-file');
            cy.contains('button', 'Create the asset').click();
        });

        cy.wait('@createAsset').then(({request, response}) => {
            expect(response.statusCode).to.eq(201);
            expect(request.body.name).to.eq('e2e-no-file');
            expect(request.body).to.not.have.property('multipart');
            expect(request.body).to.not.have.property('sourceFile');
            expect(response.body.source, 'no source file').to.not.be.ok;
            cy.wrap(response.body.id).as('assetId');
        });
        expectToastText('Asset created');
        cy.get('[role=dialog]').should('not.exist');
        cy.get('@assetId').then(id => waitForAssetIndexed(id, {'workspaces[]': ctx.workspace.id}));
        cy.reload();
        assetItem('e2e-no-file', {timeout: 60000}).should('exist');
    });

    it('creates an empty story without any file', () => {
        cy.intercept('POST', /\/assets$/).as('createAsset');

        openLeftPanelTab('Navigation');
        openUploadFromTree(ctx.workspace.id);
        cy.dialog().within(() => {
            cy.get('[role=tab]').contains('No file').click();
            cy.fieldByLabel(/^Create a story$/).click();
            // An empty story has no cover to pick, nor an asset title of its own
            cy.getBySel('upload-asset-title').should('not.exist');
            cy.contains('Story cover').should('not.exist');
            cy.contains('button', 'Create the story').should('be.disabled');
            cy.fieldByLabel('Story title').type('e2e-empty-story');
            cy.contains('button', 'Create the story').click();
        });

        cy.wait('@createAsset').then(({request, response}) => {
            expect(response.statusCode).to.eq(201);
            expect(request.body.isStory).to.eq(true);
            expect(request.body.name).to.eq('e2e-empty-story');
            expect(request.body).to.not.have.property('multipart');
            expect(response.body.storyCollection, 'story collection').to.be.ok;
        });
        expectToastText('Story created');
        cy.get('[role=dialog]').should('not.exist');
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
