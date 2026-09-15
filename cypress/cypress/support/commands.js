Cypress.Commands.add('getBySel', (selector, ...args) => {
    return cy.get(`[data-testid=${selector}]`, ...args);
});

Cypress.Commands.add('findBySel', {prevSubject: 'element'}, (subject, selector, ...args) => {
    return cy.wrap(subject).find(`[data-testid=${selector}]`, ...args);
});

/**
 * Fills the form control following a `<label>` (FormRow / LabeledControl of
 * the Next.js Databox client). Scope it with `.within()` when needed.
 */
Cypress.Commands.add('fieldByLabel', (label, options = {}) => {
    return cy
        .contains('label', label, options)
        .then($label => {
            const forId = $label.attr('for');
            if (forId && Cypress.$(`#${CSS.escape(forId)}`).length) {
                return cy.get(`#${CSS.escape(forId)}`);
            }

            const controls = 'input, textarea, select, [role=combobox], [role=switch], [role=checkbox], [role=radiogroup]';
            // LabeledControl: the control is inside the label; FormRow: it follows it
            if ($label.find(controls).length) {
                return cy.wrap($label).find(controls).first();
            }

            return cy.wrap($label).parent().find(controls).first();
        });
});

/**
 * Radix menu / context-menu / dropdown item by text.
 */
Cypress.Commands.add('menuItem', text => {
    return cy.get('[role=menuitem], [role=menuitemradio], [role=menuitemcheckbox]').contains(text);
});

/**
 * Selects an option in a Radix `Select` (SimpleSelect) identified by its
 * trigger element.
 */
Cypress.Commands.add('selectOption', {prevSubject: 'element'}, (subject, optionText) => {
    cy.wrap(subject).click();
    // The listbox is portaled to <body>: escape any `.within()` scope
    cy.document()
        .its('body')
        .find('[data-slot=select-item], [role=option]', {timeout: 10000})
        .contains(optionText)
        .click();
});

/**
 * Polls `fn` (returning a Cypress chain resolving to a boolean) until it is
 * truthy or the timeout is reached.
 */
Cypress.Commands.add('waitUntil', (fn, {timeout = 60000, interval = 2000, message = 'condition'} = {}) => {
    const started = Date.now();
    const attempt = () =>
        fn().then(ok => {
            if (ok) {
                return;
            }
            if (Date.now() - started > timeout) {
                throw new Error(`Timed out waiting for ${message}`);
            }
            cy.wait(interval, {log: false});
            attempt();
        });

    return attempt();
});

/**
 * The open Radix dialog, once its open animation settled (typing into an
 * autofocused input before that can lose keystrokes). `which`: 'first'
 * (default) or 'last' for stacked dialogs.
 */
Cypress.Commands.add('dialog', (which = 'first') => {
    return cy
        .get('[role=dialog][data-state=open]')
        .should('be.visible')
        [which]()
        .then($dialog => {
            cy.wait(300, {log: false});

            return cy.wrap($dialog, {log: false});
        });
});
