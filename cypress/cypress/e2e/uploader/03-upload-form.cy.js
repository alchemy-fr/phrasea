/**
 * Uploader — sending files with the form of the target: the information is
 * filled in while the files are sent, validated, then committed with the
 * target data.
 */
import {ensureTarget, FORM_FIELDS, iri, setBuilderForm, setTargetData, TARGETS, uniqueValue, waitForCommit} from './lib/api';
import {builderField, confirmDialog, login, pngFile, selectFiles, stepIs, visitUpload} from './lib/app';

describe('Uploader upload with a form', () => {
    let target;

    before(() => {
        login();
        ensureTarget(TARGETS.form).then(t => {
            target = t;
            setBuilderForm(t.id, FORM_FIELDS);
            setTargetData(t.id, {e2e_origin: 'cypress'});
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
        visitUpload(target.id);
    });

    function goToForm(files = ['cypress/fixtures/e2e-image.png']) {
        selectFiles(files);
        cy.getBySel('selection-next').should('contain', 'Next').click();
        stepIs('Information');
        cy.getBySel('metadata-form').should('be.visible');
    }

    it('shows the information step', () => {
        cy.getBySel('stepper').within(() => {
            cy.contains('Information').should('exist');
            cy.contains('Finalization').should('exist');
        });

        goToForm();
        cy.getBySel('builder-form').within(() => {
            cy.contains('h3', 'About the files').should('be.visible');
            cy.contains('label', 'Album').should('be.visible');
            cy.contains('label', 'Copies').should('be.visible');
        });
        builderField('__notify').should('contain', 'Notify me when done').find('[role=checkbox], [role=switch]').should('exist');
    });

    it('validates the form, then sends the batch with its information', () => {
        const album = uniqueValue('album');
        cy.intercept('POST', /\/form\/validate$/).as('validate');
        cy.intercept('POST', /\/commit$/).as('commit');

        goToForm();

        cy.getBySel('metadata-submit').click();
        builderField('album').should('contain', 'This value is required.');

        builderField('album').find('input').type(album);
        builderField('copies').find('input').type('9');
        cy.getBySel('metadata-submit').click();
        builderField('album').should('not.contain', 'This value is required.');
        builderField('copies').should('contain', 'This value should be 5 or less.');

        builderField('copies').find('input').clear().type('3');
        builderField('__notify').find('[role=checkbox], [role=switch]').click();
        cy.getBySel('metadata-submit').click();

        cy.wait('@validate').its('response.statusCode').should('eq', 200);
        cy.wait('@commit', {timeout: 60000}).then(({request, response}) => {
            expect(response.statusCode).to.eq(200);
            expect(request.body.target).to.eq(iri('targets', target.id));
            expect(request.body.files).to.have.length(1);
            expect(request.body.formData.album).to.eq(album);
            expect(String(request.body.formData.copies)).to.eq('3');
            expect(request.body.formData.__notify).to.eq(true);
        });

        stepIs('Done');
        cy.getBySel('upload-done')
            .should('contain', '1 file sent')
            .and('contain', 'You will be notified once they are processed.');
        cy.getBySel('sent-files').should('contain', 'e2e-image.png');
        cy.getBySel('other-target').should('be.visible');

        // The target data are merged into what the user entered
        waitForCommit(target.id, c => c.formData?.album === album, `commit of ${album}`).then(commit => {
            expect(commit.formData.e2e_origin).to.eq('cypress');
            expect(String(commit.formData.copies)).to.eq('3');
        });
    });

    it('goes back to the information while the files are still being sent', () => {
        const album = uniqueValue('album');
        const edited = uniqueValue('album');
        let release;
        // The file stays in transfer until it is released
        cy.intercept(
            'POST',
            /\/assets$/,
            () =>
                new Cypress.Promise(resolve => {
                    release = resolve;
                })
        );
        cy.intercept('POST', /\/commit$/).as('commit');

        goToForm();
        builderField('album').find('input').type(album);
        cy.getBySel('metadata-submit').click();

        stepIs('Finalization');
        cy.getBySel('batch-percent').should('contain', '%');
        cy.getBySel('edit-information').click();

        stepIs('Information');
        builderField('album').find('input').should('have.value', album).clear().type(edited);
        cy.getBySel('metadata-submit').click();
        stepIs('Finalization');

        cy.wrap(null).should(() => expect(release).to.be.a('function'));
        cy.then(() => release());
        cy.wait('@commit', {timeout: 60000}).its('request.body.formData.album').should('eq', edited);
        stepIs('Done');
    });

    it('adds the files dropped at the information step to the batch', () => {
        goToForm();

        cy.getBySel('toggle-files').should('contain', 'Show the file');
        cy.getBySel('batch-dropzone').selectFile(pngFile('e2e-dropped.png'), {action: 'drag-drop'});
        cy.getBySel('toggle-files').should('contain', 'Show the 2 files').click();

        cy.getBySel('batch-files').find('[data-testid=file-tile]').should('have.length', 2);
        cy.getBySel('batch-files').find('[data-testid=file-tile][title="e2e-dropped.png"]').should('exist');
        cy.getBySel('batch-files')
            .find('[data-testid=file-tile]', {timeout: 30000})
            .should($tiles => {
                expect($tiles.toArray().map(t => t.dataset.status)).to.deep.eq(['done', 'done']);
            });
        cy.getBySel('toggle-files').click();
        cy.getBySel('batch-files').should('not.exist');
    });

    it('asks before removing a file already sent', () => {
        cy.intercept('DELETE', /\/assets\/[^/]+$/).as('deleteAsset');
        goToForm(['cypress/fixtures/e2e-image.png', pngFile('e2e-removed.png')]);

        cy.getBySel('toggle-files').click();
        cy.getBySel('batch-files')
            .find('[data-testid=file-tile]', {timeout: 30000})
            .should($tiles => {
                expect($tiles.toArray().map(t => t.dataset.status)).to.deep.eq(['done', 'done']);
            });
        cy.getBySel('batch-files').find('[data-testid=file-tile][title="e2e-removed.png"]').findBySel('file-remove').click();
        cy.dialog().should('contain', 'The file was already sent: it will be deleted.');
        confirmDialog('Remove');

        cy.wait('@deleteAsset');
        cy.getBySel('batch-files').find('[data-testid=file-tile]').should('have.length', 1);
        cy.getBySel('batch-files').find('[data-testid=file-tile][title="e2e-removed.png"]').should('not.exist');
        stepIs('Information');
    });

    it('cancels the upload from the information step', () => {
        goToForm();
        builderField('album').find('input').type('Never sent');

        cy.getBySel('upload-cancel').click();
        confirmDialog('Cancel the upload');

        stepIs('Files');
        cy.getBySel('file-list').should('not.exist');
        cy.getBySel('metadata-form').should('not.exist');
    });

    it('keeps the upload going when the cancellation is dismissed', () => {
        goToForm();

        cy.getBySel('upload-cancel').click();
        confirmDialog('Continue the upload');

        stepIs('Information');
        cy.getBySel('metadata-form').should('be.visible');
    });
});
