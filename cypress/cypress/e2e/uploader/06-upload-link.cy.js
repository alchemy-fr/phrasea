/**
 * Uploader — upload links: files sent without an account to the target of
 * the link, within its limits, attributed to the creator of the link.
 */
import {uploaderUrl} from '../lib/urls';
import {
    createUploadLink,
    ensureTarget,
    FORM_FIELDS,
    removeUploadLinks,
    setBuilderForm,
    TARGETS,
    uniqueValue,
    updateUploadLink,
    waitForCommit,
} from './lib/api';
import {builderField, expectToastText, login, pngFile, selectFiles, stepIs} from './lib/app';

/** Leaves the session of the logged in user: a link needs none */
function visitAnonymously(token) {
    cy.clearAllCookies();
    cy.clearAllLocalStorage();
    cy.clearAllSessionStorage();
    cy.visit(`${uploaderUrl}/l/${token}`);
}

function visitLink(token) {
    visitAnonymously(token);
    cy.getBySel('stepper', {timeout: 30000}).should('be.visible');
}

describe('Uploader upload links', () => {
    let target;

    before(() => {
        login();
        ensureTarget(TARGETS.link).then(t => {
            target = t;
            setBuilderForm(t.id, FORM_FIELDS);
            removeUploadLinks(t.id);
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
    });

    it('sends files without an account, then again with the previous information', () => {
        let link;
        const album = uniqueValue('album');
        login();
        createUploadLink(target.id).then(l => {
            link = l;
        });

        cy.then(() => visitLink(link.token));
        cy.getBySel('upload-link-topbar').should('contain', TARGETS.link.name);
        cy.getBySel('user-menu').should('not.exist');
        cy.getBySel('download-link').should('not.exist');

        cy.intercept('POST', /\/commit$/).as('commit');
        selectFiles(['cypress/fixtures/e2e-image.png']);
        cy.getBySel('selection-next').click();
        stepIs('Information');
        // Nobody to notify behind a link
        builderField('__notify').should('not.exist');
        cy.getBySel('reuse-last-commit').should('not.exist');
        builderField('album').find('input').type(album);
        cy.getBySel('metadata-submit').click();

        cy.wait('@commit', {timeout: 60000}).then(({request, response}) => {
            expect(response.statusCode).to.eq(200);
            expect(request.headers.authorization).to.eq(`UploadLink ${link.token}`);
        });
        stepIs('Done');
        cy.getBySel('other-target').should('not.exist');

        cy.getBySel('upload-again').click();
        stepIs('Files');
        selectFiles([pngFile('e2e-second.png')]);
        cy.getBySel('selection-next').click();
        stepIs('Information');
        builderField('album').find('input').should('have.value', '');
        cy.getBySel('reuse-last-commit').click();
        builderField('album').find('input').should('have.value', album);
        cy.getBySel('metadata-submit').click();
        cy.wait('@commit', {timeout: 60000}).its('request.body.formData.album').should('eq', album);
        stepIs('Done');

        // Attributed to the creator of the link
        login();
        cy.then(() =>
            waitForCommit(target.id, c => c.formData?.album === album, `commit of ${album}`).then(commit => {
                expect(commit.userId).to.eq(link.ownerId);
                expect(commit.formData.__notify).to.be.undefined;
            })
        );
    });

    it('refuses a disabled link', () => {
        let link;
        login();
        createUploadLink(target.id).then(l => {
            link = l;
            updateUploadLink(l.id, {enabled: false});
        });

        cy.then(() => visitAnonymously(link.token));
        cy.getBySel('upload-link-error', {timeout: 30000})
            .should('have.attr', 'data-code', 'upload_link_disabled')
            .and('contain', 'This link has been disabled');
        cy.getBySel('stepper').should('not.exist');
    });

    it('refuses an unknown link', () => {
        visitAnonymously('0'.repeat(64));
        cy.getBySel('upload-link-error', {timeout: 30000})
            .should('have.attr', 'data-code', 'upload_link_invalid')
            .and('contain', 'This link is invalid');
    });

    it('stops at the number of files of the link', () => {
        let link;
        login();
        createUploadLink(target.id, {maxAssets: 1}).then(l => {
            link = l;
        });

        cy.then(() => visitLink(link.token));
        cy.getBySel('upload-constraints').should('contain', '1 file left on this link');
        // One file left: the single file mode of the uploader
        selectFiles(['cypress/fixtures/e2e-image.png', pngFile('e2e-extra.png')]);
        expectToastText('Only one file can be sent at a time.');
        cy.getBySel('file-list').should('not.exist');

        selectFiles(['cypress/fixtures/e2e-image.png']);
        cy.getBySel('selection-next').click();
        builderField('album').find('input').type(uniqueValue('album'));
        cy.getBySel('metadata-submit').click();
        stepIs('Done');

        cy.getBySel('upload-again').click();
        cy.getBySel('upload-link-exhausted').should('be.visible');
        cy.getBySel('dropzone').should('not.exist');
    });

    it('creates a link from the management screen and copies it', () => {
        const name = uniqueValue('photographer');
        login();
        cy.visit(uploaderUrl);
        cy.getBySel('nav-upload-links', {timeout: 30000}).click();
        cy.getBySel('page-title').should('contain', 'Upload links');

        cy.getBySel('upload-link-create').click();
        cy.getBySel('upload-link-expires-input').invoke('val').should('not.be.empty');
        cy.get('#upload-link-target').click();
        cy.contains('[data-slot=select-item]', TARGETS.link.name).click();
        cy.getBySel('upload-link-name-input').type(name);
        cy.getBySel('upload-link-max-assets-input').type('10');
        cy.getBySel('form-dialog').contains('button', 'Create the link').click();

        cy.getBySel('upload-link-created-url').invoke('val').should('match', /\/l\/[0-9a-f]{64}$/);
        cy.window().then(win => {
            cy.stub(win.navigator.clipboard, 'writeText').as('clipboard').resolves();
        });
        cy.getBySel('upload-link-created-copy').click();
        cy.get('@clipboard').should('have.been.calledWithMatch', /\/l\/[0-9a-f]{64}$/);
        expectToastText('Link copied');
        cy.getBySel('form-dialog').contains('button', 'Close').click();

        cy.contains('[data-testid=upload-link-row]', name).within(() => {
            cy.getBySel('upload-link-status').should('have.attr', 'data-status', 'active');
            cy.getBySel('upload-link-assets').should('contain', '0 / 10');
            cy.getBySel('upload-link-toggle').click();
            cy.getBySel('upload-link-status').should('have.attr', 'data-status', 'disabled');
        });
    });
});
