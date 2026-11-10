/**
 * Uploader — sending files to a target without any form: selection, limits,
 * upload, commit.
 */
import {ensureTarget, iri, removeFormSchemas, removeTargetParams, TARGETS, waitForCommit} from './lib/api';
import {confirmDialog, expectToastText, fileTile, login, pngFile, selectFiles, stepIs, visitUpload} from './lib/app';

describe('Uploader upload without form', () => {
    let target;

    before(() => {
        login();
        ensureTarget(TARGETS.plain).then(t => {
            target = t;
            removeFormSchemas(t.id);
            removeTargetParams(t.id);
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
        visitUpload(target.id);
    });

    it('shows the steps of an upload without form', () => {
        cy.getBySel('stepper').within(() => {
            cy.contains('Files').should('exist');
            cy.contains('Upload').should('exist');
            cy.contains('Done').should('exist');
            cy.contains('Information').should('not.exist');
        });
        stepIs('Files');
        cy.getBySel('upload-constraints').should('not.contain', 'Accepted types');
        cy.getBySel('accepted-types-info').click();
        cy.getBySel('accepted-types').should('contain', 'Accepted types');
        cy.getBySel('selection-next').should('be.disabled');
    });

    it('selects files, removes some of them', () => {
        selectFiles(['cypress/fixtures/e2e-image.png', pngFile('e2e-second.png'), pngFile('e2e-third.png')]);

        cy.getBySel('file-tile').should('have.length', 3);
        fileTile('e2e-image.png').should('have.attr', 'data-status', 'selected');
        cy.getBySel('selection-summary').should('contain', '3 files selected');
        cy.getBySel('selection-next').should('contain', 'Send 3 files').and('not.be.disabled');

        fileTile('e2e-second.png').findBySel('file-remove').click();
        cy.getBySel('file-tile').should('have.length', 2);
        fileTile('e2e-second.png').should('not.exist');
        cy.getBySel('selection-summary').should('contain', '2 files selected');

        cy.getBySel('remove-all').click();
        confirmDialog('Remove all');
        cy.getBySel('file-list').should('not.exist');
        cy.getBySel('selection-summary').should('not.exist');
        cy.getBySel('selection-next').should('be.disabled');
    });

    it('ignores a file selected twice', () => {
        // A file is known by its name, size and modification date
        const file = {...pngFile('e2e-twice.png'), lastModified: 1700000000000};
        selectFiles(file);
        cy.getBySel('file-tile').should('have.length', 1);

        selectFiles(file);
        expectToastText('1 file was already selected: it was left out.');
        cy.getBySel('file-tile').should('have.length', 1);
        cy.getBySel('selection-summary').should('contain', '1 file selected');
    });

    it('refuses a file of a type not accepted', () => {
        selectFiles({contents: Cypress.Buffer.from('Not an image'), fileName: 'e2e-notes.txt', mimeType: 'text/plain'});

        expectToastText('e2e-notes.txt is not an accepted file type.');
        cy.getBySel('upload-constraints').should('contain', 'Accepted types');
        cy.getBySel('accepted-types-info').should('not.exist');
        cy.getBySel('file-list').should('not.exist');
        cy.getBySel('selection-next').should('be.disabled');
    });

    it('sends the files and commits them to the target', () => {
        cy.intercept('POST', /\/assets$/).as('createAsset');
        cy.intercept('POST', /\/commit$/).as('commit');

        selectFiles(['cypress/fixtures/e2e-image.png', pngFile('e2e-second.png')]);
        cy.getBySel('selection-next').click();

        cy.wait('@commit', {timeout: 60000}).then(({request, response}) => {
            expect(response.statusCode).to.eq(200);
            expect(request.body.target).to.eq(iri('targets', target.id));
            expect(request.body.files).to.have.length(2);
            expect(request.body.formData).to.deep.eq({});
            cy.wrap(request.body.files).as('assetIds');
        });
        cy.get('@createAsset.all').should('have.length', 2);

        stepIs('Done');
        cy.getBySel('upload-done').should('contain', '2 files sent').and('contain', `Your files were delivered to ${TARGETS.plain.name}.`);
        cy.getBySel('success-animation').should('be.visible');
        cy.getBySel('stepper-mark-done').find('svg').should('exist');
        cy.getBySel('sent-files').should('contain', 'e2e-image.png').and('contain', 'e2e-second.png');

        cy.get('@assetIds').then(assetIds =>
            waitForCommit(target.id, c => assetIds.every(id => JSON.stringify(c.assets ?? []).includes(id)), 'commit of the batch').then(commit => {
                expect(commit.assets).to.have.length(2);
            })
        );

        // Another batch starts from an empty selection
        cy.getBySel('upload-again').click();
        stepIs('Files');
        cy.getBySel('file-list').should('not.exist');
        cy.getBySel('dropzone').should('be.visible');
    });

    it('opens the import from a URL', () => {
        cy.getBySel('download-link').click();
        cy.url().should('include', `/download/${target.id}`);
        cy.getBySel('page-title').should('contain', 'Import from a URL');
    });
});
