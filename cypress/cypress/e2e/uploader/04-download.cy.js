/**
 * Uploader — import of a file from its URL, fetched by the server.
 */
import {uploaderUrl} from '../lib/urls';
import {ensureTarget, iri, removeFormSchemas, TARGETS} from './lib/api';
import {login} from './lib/app';

/**
 * A file the Uploader API can fetch from inside the stack: a static file of
 * its own nginx (`uploader-api` network alias).
 */
const REMOTE_FILE_URL = 'http://uploader-api/logo192.png';

describe('Uploader import from a URL', () => {
    let target;

    before(() => {
        login();
        ensureTarget(TARGETS.plain).then(t => {
            target = t;
            removeFormSchemas(t.id);
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
        cy.visit(`${uploaderUrl}/download/${target.id}`);
        cy.getBySel('download-form', {timeout: 30000}).should('be.visible');
    });

    it('checks the URL before sending it', () => {
        cy.intercept('POST', /\/downloads$/, cy.spy().as('download'));

        cy.getBySel('page-title').should('contain', 'Import from a URL');
        cy.contains(`The file is fetched from its URL, then sent to ${TARGETS.plain.name}.`).should('be.visible');

        cy.getBySel('download-submit').click();
        cy.getBySel('download-form').should('contain', 'This value is required.');

        cy.get('input[name=url]').type('not a url');
        cy.getBySel('download-submit').click();
        cy.getBySel('download-form').should('contain', 'This is not a valid URL.');

        cy.get('@download').should('not.have.been.called');
    });

    it('imports a file from its URL', () => {
        cy.intercept('POST', /\/downloads$/).as('download');

        cy.get('input[name=url]').type(REMOTE_FILE_URL);
        cy.getBySel('download-submit').click();

        cy.wait('@download', {timeout: 60000}).then(({request, response}) => {
            expect(response.statusCode).to.eq(200);
            expect(request.body.url).to.eq(REMOTE_FILE_URL);
            expect(request.body.target).to.eq(iri('targets', target.id));
        });
        cy.getBySel('download-done').should('contain', `the file will be added to ${TARGETS.plain.name} once fetched`);

        cy.getBySel('download-another').click();
        cy.get('input[name=url]').should('have.value', '');
    });

    it('goes back to the targets', () => {
        cy.getBySel('download-back').click();
        cy.url().should('eq', `${uploaderUrl}/`);
    });
});
