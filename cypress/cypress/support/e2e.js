// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

import './commands'

/**
 * Next.js instruments its render with `performance.measure` in dev mode, and
 * throws on a negative timestamp while measuring a route it re-renders (the
 * `@modal` catch-all, after a redirect). It is dev-server noise, not an
 * application error: let the test go on.
 */
Cypress.on(
    'uncaught:exception',
    err => !/cannot have a negative time stamp/.test(err.message)
);
