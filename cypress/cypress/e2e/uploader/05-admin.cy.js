/**
 * Uploader — admin editors: form schemas and target data.
 */
import {uploaderUrl} from '../lib/urls';
import {
    ensureTarget,
    FORM_FIELDS,
    getFormSchemas,
    getTargetParams,
    removeTargetParams,
    setBuilderForm,
    setTargetParams,
    TARGETS,
} from './lib/api';
import {confirmDialog, expectToastText, login} from './lib/app';

function row(kind, targetName) {
    return cy.contains(`[data-testid=${kind}-row]`, targetName, {timeout: 30000});
}

describe('Uploader admin', () => {
    let target;

    before(() => {
        login();
        ensureTarget(TARGETS.admin).then(t => {
            target = t;
        });
    });

    beforeEach(() => {
        cy.viewport(1400, 900);
        login();
    });

    describe('Form schemas', () => {
        beforeEach(() => {
            setBuilderForm(target.id, FORM_FIELDS);
        });

        it('lists the form schemas and opens one in the builder', () => {
            cy.visit(`${uploaderUrl}/admin/form-schemas`);
            row('form-schema', TARGETS.admin.name).within(() => {
                cy.contains('All locales').should('exist');
                cy.getBySel('form-schema-format').should('contain', 'Form builder');
                cy.contains('a', 'Edit').click();
            });

            cy.getBySel('page-title').should('contain', `Form Schema of ${TARGETS.admin.name}`);
            cy.getBySel('form-builder-editor').should('be.visible');
            cy.getBySel('builder-palette').should('be.visible');
            cy.getBySel('builder-canvas').within(() => {
                cy.contains('About the files').should('exist');
                cy.contains('Album').should('exist');
                cy.contains('Copies').should('exist');
            });
            cy.getBySel('form-schema-save').should('be.disabled');

            cy.getBySel('form-builder-preview-tab').click();
            cy.getBySel('form-schema-preview').within(() => {
                cy.contains('label', 'Album').should('be.visible');
                cy.contains('Notify me when done').should('be.visible');
            });
        });

        it('deletes a form schema', () => {
            cy.visit(`${uploaderUrl}/admin/form-schemas`);
            row('form-schema', TARGETS.admin.name).findBySel('form-schema-delete').click();
            confirmDialog('Delete');

            cy.contains('[data-testid=form-schema-row]', TARGETS.admin.name).should('not.exist');
            getFormSchemas(target.id).should('have.length', 0);
        });

        it('opens the creation of a form schema in both formats', () => {
            cy.visit(`${uploaderUrl}/admin/form-schemas`);
            cy.getBySel('form-schema-create').click();

            cy.getBySel('page-title').should('contain', 'New Form Schema');
            cy.getBySel('form-builder-editor').should('be.visible');
            cy.getBySel('form-schema-format-liform').click();
            cy.getBySel('form-schema-editor').should('be.visible');
            cy.getBySel('form-schema-format-builder').click();
            cy.getBySel('form-builder-editor').should('be.visible');
        });
    });

    describe('Target data', () => {
        it('creates the data of a target', () => {
            removeTargetParams(target.id);
            cy.visit(`${uploaderUrl}/admin/target-param`);
            cy.getBySel('target-params-create').click();
            cy.getBySel('target-params-editor').should('be.visible');

            cy.getBySel('target-params-editor').find('[role=combobox]').click();
            cy.get('[cmdk-input]').type(TARGETS.admin.name);
            cy.contains('[cmdk-item]', TARGETS.admin.name).click();
            cy.getBySel('target-params-save').click();

            expectToastText('Target data saved');
            cy.url().should('match', /\/admin\/target-param\/[^/]+\/edit$/);
            cy.getBySel('page-title').should('contain', `Target Data of ${TARGETS.admin.name}`);
            getTargetParams(target.id).then(list => {
                expect(list).to.have.length(1);
                expect(list[0].data).to.deep.eq({my_var: 'my_value'});
            });
        });

        it('deletes the data of a target', () => {
            setTargetParams(target.id, {e2e: true});
            cy.visit(`${uploaderUrl}/admin/target-param`);
            row('target-params', TARGETS.admin.name).findBySel('target-params-delete').click();
            confirmDialog('Delete');

            cy.contains('[data-testid=target-params-row]', TARGETS.admin.name).should('not.exist');
            getTargetParams(target.id).should('have.length', 0);
        });
    });
});
